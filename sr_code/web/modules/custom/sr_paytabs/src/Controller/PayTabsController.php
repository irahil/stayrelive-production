<?php

namespace Drupal\sr_paytabs\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Controller\ControllerBase;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PayTabsController extends ControllerBase {

  protected $httpClient;

  public function __construct(ClientInterface $http_client) {
    $this->httpClient = $http_client;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * Step 3 — Initiate Payment: generate invoice and POST to PayTabs.
   */
public function initiatePayment($booking_id) {
  $booking = \Drupal::entityTypeManager()->getStorage('booking')->load($booking_id);

  if (!$booking) {
    throw new NotFoundHttpException();
  }

  if ($booking->getOwnerId() != \Drupal::currentUser()->id()) {
    throw new AccessDeniedHttpException();
  }

  $status = $booking->get('field_status')->getString();
  if (!in_array($status, ['payment_pending', 'payment_failed', 'payment_initiated', 'payment_bank_pending', 'reserved_unpaid'], TRUE)) {
    \Drupal::messenger()->addWarning($this->t('This booking cannot be paid at this time.'));
    return $this->redirect('sr.booking.search');
  }

  $config     = $this->config('sr_paytabs.settings');
  $profile_id = $config->get('profile_id');
  $server_key = $config->get('server_key');
  $endpoint   = rtrim($config->get('endpoint') ?: 'https://secure.paytabs.sa', '/');
  $verify_ssl = !(bool) $config->get('sandbox_mode');

  if (empty($profile_id) || empty($server_key)) {
    \Drupal::logger('sr_paytabs')->error('PayTabs credentials not configured.');
    \Drupal::messenger()->addError($this->t('Payment is temporarily unavailable. Please contact support.'));
    return $this->redirect('sr.search');
  }

  $property_id = $booking->get('field_property_id')->getString();
  $node = Node::load($property_id);
  if (!$node) {
    throw new NotFoundHttpException();
  }

  try {
    $currency_tid  = $booking->get('field_currency_code')->getString();
    $currency_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($currency_tid);
    $currency      = $currency_term ? $currency_term->label() : 'USD';
    $amount        = ceil((float) $booking->get('field_price')->getString());
    $cart_id       = 'SR-' . $booking_id . '-' . \Drupal::time()->getRequestTime();

    $callback_override = $config->get('callback_url_override');
    $return_override   = $config->get('return_url_override');

    $callback_generated = NULL;
    $return_generated = NULL;

    if (!empty($callback_override)) {
      $callback_url = rtrim($callback_override, '/');
    }
    else {
      $callback_generated = Url::fromRoute('sr_paytabs.ipn', [], ['absolute' => TRUE])->toString(TRUE);
      $callback_url = $callback_generated->getGeneratedUrl();
    }

    $return_token = hash_hmac('sha256', 'return:' . $booking_id, $server_key);

    if (!empty($return_override)) {
      $return_base = rtrim($return_override, '/') . '/payment/return/' . $booking_id;
    }
    else {
      $return_generated = Url::fromRoute('sr_paytabs.return', ['booking_id' => $booking_id], ['absolute' => TRUE])->toString(TRUE);
      $return_base = $return_generated->getGeneratedUrl();
    }

    $return_url = $return_base . '?tok=' . $return_token;

    $payload = [
      'profile_id'       => (int) $profile_id,
      'tran_type'        => 'sale',
      'tran_class'       => 'ecom',
      'cart_id'          => $cart_id,
      'cart_currency'    => $currency,
      'cart_amount'      => $amount,
      'cart_description' => 'Apartment Booking: ' . $node->getTitle() . ' [#' . $booking_id . ']',
      'return'           => $return_url,
      'callback'         => $callback_url,
      'customer_details' => [
        'name'    => $booking->get('field_name')->getString(),
        'email'   => $booking->get('field_email')->getString(),
        'phone'   => $booking->get('field_phone_code')->getString() . $booking->get('field_phone_number')->getString(),
        'street1' => $node->get('field_display_address')->getString() ?: 'N/A',
        'city'    => $node->get('field_location_town')->getString() ?: 'N/A',
        'country' => strtoupper($node->get('field_location_country_code')->getString() ?: 'SA'),
      ],
    ];

    \Drupal::logger('sr_paytabs')->debug('Initiating payment for booking @id — endpoint: @endpoint, sandbox_mode: @sandbox, profile_id: @profile, cart_id: @cart, amount: @amount @currency, return: @return, callback: @callback', [
      '@id'       => $booking_id,
      '@endpoint' => $endpoint,
      '@sandbox'  => $verify_ssl ? 'false' : 'true',
      '@profile'  => $profile_id,
      '@cart'     => $cart_id,
      '@amount'   => $amount,
      '@currency' => $currency,
      '@return'   => $return_url,
      '@callback' => $callback_url,
    ]);

    $api_response = $this->httpClient->post($endpoint . '/payment/request', [
      'json'            => $payload,
      'headers'         => ['authorization' => $server_key],
      'http_errors'     => FALSE,
      'verify'          => $verify_ssl,
      'timeout'         => 30,
      'connect_timeout' => 10,
    ]);

    $data = json_decode($api_response->getBody()->getContents(), TRUE);

    \Drupal::logger('sr_paytabs')->notice('Payment initiation response for booking @id (HTTP @status): @data', [
      '@id'     => $booking_id,
      '@status' => $api_response->getStatusCode(),
      '@data'   => json_encode($data),
    ]);

    if (!empty($data['redirect_url'])) {
      try {
        \Drupal::database()->insert('sr_payment_transactions')->fields([
          'booking_id'     => $booking_id,
          'cart_id'        => $cart_id,
          'tran_ref'       => $data['tran_ref'] ?? '',
          'payment_status' => 'initiated',
          'amount'         => $amount,
          'currency'       => $currency,
          'raw_response'   => json_encode($data),
          'created'        => \Drupal::time()->getRequestTime(),
          'updated'        => \Drupal::time()->getRequestTime(),
        ])->execute();

        \Drupal::logger('sr_paytabs')->debug('Transaction row written for booking @id, cart_id @cart, tran_ref @ref.', [
          '@id'   => $booking_id,
          '@cart' => $cart_id,
          '@ref'  => $data['tran_ref'] ?? '',
        ]);
      }
      catch (\Throwable $e) {
        \Drupal::logger('sr_paytabs')->error('DB write failed for booking @id after PayTabs accepted the request (cart_id @cart, tran_ref @ref): @class: @msg in @file:@line<br><pre>@trace</pre>', [
          '@id'    => $booking_id,
          '@cart'  => $cart_id,
          '@ref'   => $data['tran_ref'] ?? '',
          '@class' => get_class($e),
          '@msg'   => $e->getMessage(),
          '@file'  => $e->getFile(),
          '@line'  => $e->getLine(),
          '@trace' => $e->getTraceAsString(),
        ]);
        throw $e;
      }

      $renderer = \Drupal::service('renderer');
      $context = new RenderContext();

      $renderer->executeInRenderContext($context, function () use ($booking) {
        $booking->set('field_status', 'payment_initiated');
        $booking->save();
      });

      $payment_url = $data['redirect_url'];
      $response = new TrustedRedirectResponse($payment_url, 302);
      $response->addCacheableDependency((new CacheableMetadata())->setCacheMaxAge(0));

      if ($callback_generated) {
        $response->addCacheableDependency($callback_generated);
      }
      if ($return_generated) {
        $response->addCacheableDependency($return_generated);
      }
      if (!$context->isEmpty()) {
        $response->addCacheableDependency($context->pop());
      }

      return $response;
    }

    $error_msg = $data['message'] ?? 'No redirect URL returned by PayTabs.';
    \Drupal::logger('sr_paytabs')->error('PayTabs initiation failed for booking @id (HTTP @status): @msg', [
      '@id'     => $booking_id,
      '@status' => $api_response->getStatusCode(),
      '@msg'    => $error_msg,
    ]);
    \Drupal::messenger()->addError($this->t('Payment could not be initiated: @msg', ['@msg' => $error_msg]));
    return $this->redirect('sr.booking.search');

  }
  catch (\Throwable $e) {
    \Drupal::logger('sr_paytabs')->error('PayTabs initiation exception for booking @id: @class: @msg in @file:@line<br><pre>@trace</pre>', [
      '@id'    => $booking_id,
      '@class' => get_class($e),
      '@msg'   => $e->getMessage(),
      '@file'  => $e->getFile(),
      '@line'  => $e->getLine(),
      '@trace' => $e->getTraceAsString(),
    ]);
    \Drupal::messenger()->addError($this->t('Payment service is temporarily unavailable. Please try again later.'));
    return $this->redirect('sr.booking.search');
  }
}

  /**
   * Step 5 — Handle Response: browser return from PayTabs hosted page.
   */
  public function handleReturn($booking_id) {
    $booking = \Drupal::entityTypeManager()->getStorage('booking')->load($booking_id);
    if (!$booking) {
      throw new NotFoundHttpException();
    }

    $config     = $this->config('sr_paytabs.settings');
    $server_key = $config->get('server_key');
    $tok        = \Drupal::request()->query->get('tok', '');
    $valid_tok  = !empty($tok) && hash_equals(
      hash_hmac('sha256', 'return:' . $booking_id, $server_key),
      $tok
    );

    \Drupal::logger('sr_paytabs')->debug('handleReturn for booking @id: tok_present=@tok_present, valid_tok=@valid_tok, authenticated=@auth, current_uid=@uid, owner_uid=@owner_uid', [
      '@id'         => $booking_id,
      '@tok_present' => $tok !== '' ? 'yes' : 'no',
      '@valid_tok'  => $valid_tok ? 'yes' : 'no',
      '@auth'       => \Drupal::currentUser()->isAuthenticated() ? 'yes' : 'no',
      '@uid'        => \Drupal::currentUser()->id(),
      '@owner_uid'  => $booking->getOwnerId(),
    ]);

    if ($valid_tok) {
      // Valid HMAC token — cross-site POST from PayTabs dropped the session cookie
      // (SameSite=Lax). Re-login the booking owner so the user isn't left logged out.
      if (!\Drupal::currentUser()->isAuthenticated()) {
        $owner = \Drupal\user\Entity\User::load($booking->getOwnerId());
        if ($owner && $owner->isActive()) {
          // Clear any stashed post-login redirect (set by sr_share_form_alter
          // from earlier anonymous browsing), and flag this as a silent
          // re-login so hook_user_login() implementations like
          // sr_share_user_login() don't hard-redirect away from the payment
          // return page — see sr_share_user_login()'s sr_silent_login check.
          $session = \Drupal::service('session');
          $session->remove('destination');
          $session->remove('flag_node_id');
          \Drupal::request()->attributes->set('sr_silent_login', TRUE);

          user_login_finalize($owner);
          \Drupal::logger('sr_paytabs')->debug('handleReturn for booking @id: auto-logged-in owner uid @owner_uid via valid token.', [
            '@id'        => $booking_id,
            '@owner_uid' => $owner->id(),
          ]);
        }
        else {
          \Drupal::logger('sr_paytabs')->warning('handleReturn for booking @id: valid token but owner uid @owner_uid missing or inactive — could not auto-login.', [
            '@id'        => $booking_id,
            '@owner_uid' => $booking->getOwnerId(),
          ]);
        }
      }
    }
    else {
      // No valid token — fall back to session-based ownership check.
      if (!\Drupal::currentUser()->isAuthenticated()) {
        \Drupal::logger('sr_paytabs')->warning('handleReturn for booking @id: invalid/missing token and no session — redirecting to login.', [
          '@id' => $booking_id,
        ]);
        return $this->redirect('user.login', [], [
          'query' => ['destination' => '/payment/return/' . $booking_id],
        ]);
      }
      if ($booking->getOwnerId() != \Drupal::currentUser()->id()) {
        \Drupal::logger('sr_paytabs')->error('handleReturn for booking @id: invalid token and current uid @uid does not match owner uid @owner_uid — access denied.', [
          '@id'        => $booking_id,
          '@uid'       => \Drupal::currentUser()->id(),
          '@owner_uid' => $booking->getOwnerId(),
        ]);
        throw new AccessDeniedHttpException();
      }
    }

    $status      = $booking->get('field_status')->getString();
    $property_id = $booking->get('field_property_id')->getString();
    $node        = Node::load($property_id);
    $attempt     = max(0, (int) \Drupal::request()->query->get('attempt', 0));

    $transaction = \Drupal::database()->select('sr_payment_transactions', 't')
      ->fields('t')
      ->condition('booking_id', $booking_id)
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    // If IPN hasn't arrived yet, query PayTabs directly so we don't rely solely on IPN delivery.
    if ($status === 'payment_initiated' && !empty($transaction['tran_ref'])) {
      $live = $this->queryTransaction($transaction['tran_ref']);
      $live_status = $live['payment_result']['response_status'] ?? '';

      if (!empty($live_status)) {
        $payment_status = ($live_status === 'A') ? 'approved'
          : (in_array($live_status, ['P', 'H']) ? 'payment_bank_pending' : 'declined');
        $booking_status = $this->mapResponseStatus($live_status);

        \Drupal::database()->update('sr_payment_transactions')
          ->fields([
            'payment_status'   => $payment_status,
            'response_status'  => $live_status,
            'response_code'    => $live['payment_result']['response_code']    ?? '',
            'response_message' => $live['payment_result']['response_message'] ?? '',
            'raw_response'     => json_encode($live),
            'updated'          => \Drupal::time()->getRequestTime(),
          ])
          ->condition('id', $transaction['id'])
          ->execute();

        $booking->set('field_status', $booking_status);
        $booking->save();

        if ($booking_status === 'payment_confirmed') {
          \Drupal::service('sr.services')->emailBooking($booking_id);
        }

        $status      = $booking_status;
        $transaction = array_merge($transaction, [
          'payment_status'   => $payment_status,
          'response_status'  => $live_status,
          'response_code'    => $live['payment_result']['response_code']    ?? '',
          'response_message' => $live['payment_result']['response_message'] ?? '',
        ]);
      }
    }

    $return_tok = hash_hmac('sha256', 'return:' . $booking_id, $server_key);

    // Pre-extract display-ready values the template can't derive on its own
    // (serialized image blob, raw datetime strings) — mirrors the pattern
    // used in BookingForm.php.
    $property_image = '';
    if ($node && $node->hasField('field_media') && $node->get('field_media')->value) {
      $arr_image = unserialize($node->get('field_media')->getString());
      $property_image = $arr_image[0]['url'] ?? '';
    }

    $checkin_formatted  = $booking->hasField('field_from_date') && $booking->get('field_from_date')->value
      ? date('jS F Y', strtotime($booking->get('field_from_date')->value)) : '';
    $checkout_formatted = $booking->hasField('field_to_date') && $booking->get('field_to_date')->value
      ? date('jS F Y', strtotime($booking->get('field_to_date')->value)) : '';

    \Drupal::logger('sr_paytabs')->debug('handleReturn for booking @id: rendering return page — final status=@status, is_success=@is_success, is_pending=@is_pending, attempt=@attempt.', [
      '@id'         => $booking_id,
      '@status'     => $status,
      '@is_success' => ($status === 'payment_confirmed') ? 'yes' : 'no',
      '@is_pending' => in_array($status, ['payment_initiated', 'payment_pending', 'payment_bank_pending']) ? 'yes' : 'no',
      '@attempt'    => $attempt,
    ]);

    return [
      '#theme'            => 'sr_paytabs_return',
      '#is_success'       => ($status === 'payment_confirmed'),
      '#is_pending'       => in_array($status, ['payment_initiated', 'payment_pending', 'payment_bank_pending']),
      '#booking_id'       => $booking_id,
      '#booking'          => $booking,
      '#property'         => $node,
      '#property_image'   => $property_image,
      '#checkin'          => $checkin_formatted,
      '#checkout'         => $checkout_formatted,
      '#transaction'      => $transaction,
      '#attempt'          => $attempt,
      '#return_tok'       => $return_tok,
      '#attached'         => ['library' => ['sr/sr_lib', 'sr_paytabs/payment_return']],
    ];
  }

  /**
   * Admin payments dashboard.
   */
  public function paymentsDashboard() {
    $db = \Drupal::database();

    $request = \Drupal::request();
    $filters = [
      'status' => $request->query->get('status', ''),
      'source' => $request->query->get('source', ''),
      'from'   => $request->query->get('from', ''),
      'to'     => $request->query->get('to', ''),
    ];

    // One GROUP BY query replaces 6 separate count/sum queries.
    $stats = ['total' => 0, 'approved' => 0, 'declined' => 0, 'refunded' => 0, 'initiated' => 0, 'total_collected' => 0.0];
    try {
      $stats_rows = $db->query(
        "SELECT payment_status, COUNT(*) AS cnt, SUM(amount) AS total_amt
         FROM {sr_payment_transactions}
         GROUP BY payment_status"
      )->fetchAll();
      foreach ($stats_rows as $row) {
        $stats[$row->payment_status] = (int) $row->cnt;
        $stats['total'] += (int) $row->cnt;
        if ($row->payment_status === 'approved') {
          $stats['total_collected'] = (float) $row->total_amt;
        }
      }
    }
    catch (\Exception $e) {
      return ['#markup' => $this->t('Payment transactions table not found. Please run: drush updb')];
    }

    // Transactions query with joins.
    // Only select columns shown in the dashboard — raw_response (TEXT) is excluded.
    $query = $db->select('sr_payment_transactions', 't');
    $query->fields('t', ['id', 'booking_id', 'cart_id', 'tran_ref', 'payment_status',
      'response_status', 'response_code', 'response_message', 'amount', 'currency', 'created', 'updated']);
    $query->leftJoin('booking__field_name',        'fn',  'fn.entity_id  = t.booking_id AND fn.deleted = 0');
    $query->leftJoin('booking__field_email',       'fe',  'fe.entity_id  = t.booking_id AND fe.deleted = 0');
    $query->leftJoin('booking__field_property_id', 'fp',  'fp.entity_id  = t.booking_id AND fp.deleted = 0');
    $query->leftJoin('node_field_data',            'n',   'n.nid         = fp.field_property_id_target_id');
    $query->leftJoin('node__field_property_source','ps',  'ps.entity_id  = fp.field_property_id_target_id AND ps.deleted = 0');
    $query->addField('fn', 'field_name_value',               'guest_name');
    $query->addField('fe', 'field_email_value',              'guest_email');
    $query->addField('n',  'title',                          'property_title');
    $query->addField('ps', 'field_property_source_value',    'property_source');

    if (!empty($filters['status'])) {
      $query->condition('t.payment_status', $filters['status']);
    }
    if (!empty($filters['source'])) {
      $query->condition('ps.field_property_source_value', $filters['source']);
    }
    if (!empty($filters['from'])) {
      $query->condition('t.created', strtotime($filters['from']), '>=');
    }
    if (!empty($filters['to'])) {
      $query->condition('t.created', strtotime($filters['to'] . ' 23:59:59'), '<=');
    }

    $query->orderBy('t.id', 'DESC');
    $transactions = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->limit(50)
      ->execute()
      ->fetchAll();


    // Attach CSRF-protected refund URLs so the template never builds GET links unprotected.
    $csrf = \Drupal::csrfToken();
    foreach ($transactions as $txn) {
      if ($txn->payment_status === 'approved') {
        $url = Url::fromRoute('sr_paytabs.refund', ['booking_id' => $txn->booking_id]);
        $txn->refund_url = $url->setOption('query', ['token' => $csrf->get($url->getInternalPath())])->toString();
      }
    }

    return [
      '#theme'        => 'sr_paytabs_dashboard',
      '#stats'        => $stats,
      '#transactions' => $transactions,
      '#filters'      => $filters,
      '#pager'        => ['#type' => 'pager'],
      '#attached'     => ['library' => ['sr/sr_lib']],
    ];
  }

  /**
   * Step 6 — IPN / Callback: server-to-server notification from PayTabs.
   */
  public function handleIpn() {
    $request    = \Drupal::request();
    $raw_body   = $request->getContent();
    $data       = json_decode($raw_body, TRUE);
    if (empty($data)) {
      $data = $request->request->all();
    }

    $config     = $this->config('sr_paytabs.settings');
    $server_key = $config->get('server_key');

    if (!$this->verifyIpnSignature($raw_body, $request->headers->get('Signature', ''), $server_key)) {
      \Drupal::logger('sr_paytabs')->warning('IPN rejected: invalid signature.');
      return new JsonResponse(['status' => 'error', 'message' => 'Invalid signature'], 403);
    }

    \Drupal::logger('sr_paytabs_ipn')->notice('IPN received: @data', ['@data' => json_encode($data)]);

    if (empty($data['cart_id'])) {
      return new JsonResponse(['status' => 'error', 'message' => 'Missing cart_id'], 400);
    }

    $cart_id          = $data['cart_id'];
    $tran_ref         = $data['tran_ref'] ?? '';
    $response_status  = $data['payment_result']['response_status'] ?? '';
    $response_code    = $data['payment_result']['response_code'] ?? '';
    $response_message = $data['payment_result']['response_message'] ?? '';

    $transaction = \Drupal::database()->select('sr_payment_transactions', 't')
      ->fields('t')
      ->condition('cart_id', $cart_id)
      ->execute()
      ->fetchAssoc();

    if (!$transaction) {
      \Drupal::logger('sr_paytabs')->warning('IPN for unknown cart_id: @id', ['@id' => $cart_id]);
      return new JsonResponse(['status' => 'error', 'message' => 'Unknown transaction'], 404);
    }

    $booking_id     = $transaction['booking_id'];
    $booking_status = $this->mapResponseStatus($response_status);
    $payment_status = ($response_status === 'A') ? 'approved' : 'declined';

    \Drupal::database()->update('sr_payment_transactions')
      ->fields([
        'tran_ref'         => $tran_ref,
        'payment_status'   => $payment_status,
        'response_status'  => $response_status,
        'response_code'    => $response_code,
        'response_message' => $response_message,
        'raw_response'     => json_encode($data),
        'updated'          => \Drupal::time()->getRequestTime(),
      ])
      ->condition('cart_id', $cart_id)
      ->execute();

    $booking = \Drupal::entityTypeManager()->getStorage('booking')->load($booking_id);
    if ($booking) {
      $current_status = $booking->get('field_status')->getString();
      if ($current_status !== 'payment_confirmed') {
        $booking->set('field_status', $booking_status);
        $booking->save();

        if ($booking_status === 'payment_confirmed') {
          \Drupal::service('sr.services')->emailBooking($booking_id);
        }
      }
    }

    return new JsonResponse(['status' => 'ok']);
  }

  /**
   * Step 7 — Refund transaction (admin only).
   */
  public function refundTransaction($booking_id) {
    if (!\Drupal::currentUser()->hasRole('administrator')) {
      throw new AccessDeniedHttpException();
    }

    $booking = \Drupal::entityTypeManager()->getStorage('booking')->load($booking_id);
    if (!$booking) {
      throw new NotFoundHttpException();
    }

    $transaction = \Drupal::database()->select('sr_payment_transactions', 't')
      ->fields('t')
      ->condition('booking_id', $booking_id)
      ->condition('payment_status', 'approved')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$transaction || empty($transaction['tran_ref'])) {
      \Drupal::messenger()->addError($this->t('No approved transaction found for booking #@id.', ['@id' => $booking_id]));
      return $this->redirect('entity.booking.canonical', ['booking' => $booking_id]);
    }

    $config     = $this->config('sr_paytabs.settings');
    $endpoint   = rtrim($config->get('endpoint') ?: 'https://secure.paytabs.sa', '/');
    $verify_ssl = !(bool) $config->get('sandbox_mode');

    $payload = [
      'profile_id'       => (int) $config->get('profile_id'),
      'tran_type'        => 'refund',
      'tran_class'       => 'ecom',
      'tran_ref'         => $transaction['tran_ref'],
      'cart_id'          => $transaction['cart_id'],
      'cart_currency'    => $transaction['currency'],
      'cart_amount'      => (float) $transaction['amount'],
      'cart_description' => 'Refund for Booking #' . $booking_id,
    ];

    try {
      $response = $this->httpClient->post($endpoint . '/payment/request', [
        'json'            => $payload,
        'headers'         => ['authorization' => $config->get('server_key')],
        'http_errors'     => FALSE,
        'verify'          => $verify_ssl,
        'timeout'         => 30,
        'connect_timeout' => 10,
      ]);

      $data = json_decode($response->getBody()->getContents(), TRUE);

      if (!empty($data['payment_result']['response_status']) && $data['payment_result']['response_status'] === 'A') {
        $booking->set('field_status', 'refunded');
        $booking->save();

        \Drupal::database()->insert('sr_payment_transactions')->fields([
          'booking_id'       => $booking_id,
          'cart_id'          => $transaction['cart_id'] . '-REFUND',
          'tran_ref'         => $data['tran_ref'] ?? '',
          'payment_status'   => 'refunded',
          'response_status'  => 'A',
          'response_code'    => $data['payment_result']['response_code'] ?? '',
          'response_message' => 'Refund processed successfully',
          'amount'           => $transaction['amount'],
          'currency'         => $transaction['currency'],
          'raw_response'     => json_encode($data),
          'created'          => \Drupal::time()->getRequestTime(),
          'updated'          => \Drupal::time()->getRequestTime(),
        ])->execute();

        \Drupal::messenger()->addStatus($this->t('Refund processed. Ref: @ref', ['@ref' => $data['tran_ref'] ?? 'N/A']));
      } else {
        $error_msg = $data['message'] ?? ($data['payment_result']['response_message'] ?? 'Unknown error');
        \Drupal::messenger()->addError($this->t('Refund failed: @msg', ['@msg' => $error_msg]));
      }
    } catch (\Throwable $e) {
      \Drupal::logger('sr_paytabs')->error('Refund exception for booking @id: @class: @msg in @file:@line<br><pre>@trace</pre>', [
        '@id'    => $booking_id,
        '@class' => get_class($e),
        '@msg'   => $e->getMessage(),
        '@file'  => $e->getFile(),
        '@line'  => $e->getLine(),
        '@trace' => $e->getTraceAsString(),
      ]);
      \Drupal::messenger()->addError($this->t('Refund service error. Please try again.'));
    }

    return $this->redirect('entity.booking.canonical', ['booking' => $booking_id]);
  }

  /**
   * Step 7 — Query transaction status from PayTabs API.
   */
  protected function queryTransaction($tran_ref) {
    $config     = $this->config('sr_paytabs.settings');
    $endpoint   = rtrim($config->get('endpoint') ?: 'https://secure.paytabs.sa', '/');
    $verify_ssl = !(bool) $config->get('sandbox_mode');

    try {
      $response = $this->httpClient->post($endpoint . '/payment/query', [
        'json'            => [
          'profile_id' => (int) $config->get('profile_id'),
          'tran_ref'   => $tran_ref,
        ],
        'headers'         => ['authorization' => $config->get('server_key')],
        'http_errors'     => FALSE,
        'verify'          => $verify_ssl,
        'timeout'         => 8,
        'connect_timeout' => 4,
      ]);
      $body = $response->getBody()->getContents();
      \Drupal::logger('sr_paytabs')->debug('Query response for @ref (HTTP @status): @body', [
        '@ref'    => $tran_ref,
        '@status' => $response->getStatusCode(),
        '@body'   => $body,
      ]);
      return json_decode($body, TRUE);
    } catch (\Throwable $e) {
      \Drupal::logger('sr_paytabs')->error('Query exception for @ref: @class: @msg in @file:@line<br><pre>@trace</pre>', [
        '@ref'   => $tran_ref,
        '@class' => get_class($e),
        '@msg'   => $e->getMessage(),
        '@file'  => $e->getFile(),
        '@line'  => $e->getLine(),
        '@trace' => $e->getTraceAsString(),
      ]);
      return NULL;
    }
  }

  /**
   * Verifies the PayTabs IPN signature.
   * PayTabs sends HMAC-SHA256 of the raw request body in the 'Signature' HTTP header.
   * See: https://support.paytabs.com/en/support/solutions/articles/60000710069
   */
  protected function verifyIpnSignature(string $raw_body, string $received_signature, string $server_key): bool {
    if (empty($received_signature) || empty($server_key)) {
      return FALSE;
    }

    $computed = hash_hmac('sha256', $raw_body, $server_key);

    return hash_equals($computed, $received_signature);
  }

  /**
   * Admin payment detail view for a booking — timeline, logs, and actions.
   */
  public function paymentView($booking_id) {
    $booking = \Drupal::entityTypeManager()->getStorage('booking')->load($booking_id);
    if (!$booking) {
      throw new NotFoundHttpException();
    }

    $property_id   = $booking->get('field_property_id')->getString();
    $node          = Node::load($property_id);
    $currency_tid  = $booking->get('field_currency_code')->getString();
    $currency_term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($currency_tid);
    $currency      = $currency_term ? $currency_term->label() : 'USD';

    $booking_data = [
      'id'       => $booking_id,
      'status'   => $booking->get('field_status')->getString(),
      'name'     => $booking->get('field_name')->getString(),
      'email'    => $booking->get('field_email')->getString(),
      'phone'    => $booking->get('field_phone_code')->getString() . $booking->get('field_phone_number')->getString(),
      'price'    => (float) $booking->get('field_price')->getString(),
      'currency' => $currency,
      'checkin'  => $booking->hasField('field_checkin')  ? $booking->get('field_checkin')->getString()  : '',
      'checkout' => $booking->hasField('field_checkout') ? $booking->get('field_checkout')->getString() : '',
      'adults'   => $booking->hasField('field_adults')   ? $booking->get('field_adults')->getString()   : '',
      'children' => $booking->hasField('field_children') ? $booking->get('field_children')->getString() : '',
      'nights'   => $booking->hasField('field_nights')   ? $booking->get('field_nights')->getString()   : '',
    ];

    $property_data = $node ? [
      'title'   => $node->getTitle(),
      'nid'     => $node->id(),
      'address' => $node->hasField('field_display_address')       ? $node->get('field_display_address')->getString()       : '',
      'source'  => $node->hasField('field_property_source')       ? $node->get('field_property_source')->getString()       : '',
      'country' => $node->hasField('field_location_country_code') ? $node->get('field_location_country_code')->getString() : '',
      'city'    => $node->hasField('field_location_town')         ? $node->get('field_location_town')->getString()         : '',
    ] : [];

    $transactions = \Drupal::database()->select('sr_payment_transactions', 't')
      ->fields('t')
      ->condition('booking_id', $booking_id)
      ->orderBy('created', 'ASC')
      ->execute()
      ->fetchAll();

    $logs = [];
    if (\Drupal::moduleHandler()->moduleExists('dblog')) {
      try {
        $results = \Drupal::database()->select('watchdog', 'w')
          ->fields('w', ['wid', 'type', 'severity', 'message', 'variables', 'timestamp'])
          ->condition('type', ['sr_paytabs', 'sr_paytabs_ipn'], 'IN')
          ->condition('variables', '%' . $booking_id . '%', 'LIKE')
          ->orderBy('timestamp', 'ASC')
          ->execute()
          ->fetchAll();

        foreach ($results as $log) {
          $vars    = @unserialize($log->variables);
          $message = $log->message;
          if (is_array($vars)) {
            foreach ($vars as $key => $val) {
              $message = str_replace($key, htmlspecialchars((string) $val), $message);
            }
          }
          $logs[] = [
            'time'     => $log->timestamp,
            'type'     => $log->type,
            'severity' => (int) $log->severity,
            'message'  => strip_tags($message),
          ];
        }
      }
      catch (\Exception $e) {}
    }

    $csrf       = \Drupal::csrfToken();
    $refund_url = NULL;
    $resend_url = NULL;
    $query_url  = NULL;
    $latest     = !empty($transactions) ? end($transactions) : NULL;

    if ($latest && $latest->payment_status === 'approved') {
      $r = Url::fromRoute('sr_paytabs.refund', ['booking_id' => $booking_id]);
      $refund_url = $r->setOption('query', ['token' => $csrf->get($r->getInternalPath())])->toString();

      $e = Url::fromRoute('sr_paytabs.resend_email', ['booking_id' => $booking_id]);
      $resend_url = $e->setOption('query', ['token' => $csrf->get($e->getInternalPath())])->toString();
    }

    if ($latest && !empty($latest->tran_ref)) {
      $q = Url::fromRoute('sr_paytabs.query_status', ['booking_id' => $booking_id]);
      $query_url = $q->setOption('query', ['token' => $csrf->get($q->getInternalPath())])->toString();
    }

    return [
      '#theme'        => 'sr_paytabs_view',
      '#booking'      => $booking_data,
      '#property'     => $property_data,
      '#transactions' => $transactions,
      '#logs'         => $logs,
      '#refund_url'   => $refund_url,
      '#resend_url'   => $resend_url,
      '#query_url'    => $query_url,
      '#attached'     => ['library' => ['sr/sr_lib']],
    ];
  }

  /**
   * Re-send booking confirmation email (admin action).
   */
  public function resendEmail($booking_id) {
    $booking = \Drupal::entityTypeManager()->getStorage('booking')->load($booking_id);
    if (!$booking) {
      throw new NotFoundHttpException();
    }

    try {
      \Drupal::service('sr.services')->emailBooking($booking_id);
      \Drupal::messenger()->addStatus($this->t('Confirmation email re-sent for booking #@id.', ['@id' => $booking_id]));
    }
    catch (\Exception $e) {
      \Drupal::logger('sr_paytabs')->error('Resend email failed for booking @id: @msg', ['@id' => $booking_id, '@msg' => $e->getMessage()]);
      \Drupal::messenger()->addError($this->t('Failed to send email: @msg', ['@msg' => $e->getMessage()]));
    }

    return $this->redirect('sr_paytabs.payment_view', ['booking_id' => $booking_id]);
  }

  /**
   * Query live transaction status from PayTabs and update local record.
   */
  public function queryTransactionStatus($booking_id) {
    $transaction = \Drupal::database()->select('sr_payment_transactions', 't')
      ->fields('t')
      ->condition('booking_id', $booking_id)
      ->isNotNull('tran_ref')
      ->condition('tran_ref', '', '<>')
      ->orderBy('id', 'DESC')
      ->range(0, 1)
      ->execute()
      ->fetchAssoc();

    if (!$transaction) {
      \Drupal::messenger()->addWarning($this->t('No queryable transaction found for booking #@id.', ['@id' => $booking_id]));
      return $this->redirect('sr_paytabs.payment_view', ['booking_id' => $booking_id]);
    }

    $data = $this->queryTransaction($transaction['tran_ref']);

    if ($data) {
      $response_status  = $data['payment_result']['response_status']  ?? '';
      $response_code    = $data['payment_result']['response_code']    ?? '';
      $response_message = $data['payment_result']['response_message'] ?? '';

      if (!empty($response_status)) {
        $payment_status = ($response_status === 'A') ? 'approved'
          : (in_array($response_status, ['P', 'H']) ? 'payment_bank_pending' : 'declined');

        \Drupal::database()->update('sr_payment_transactions')
          ->fields([
            'payment_status'   => $payment_status,
            'response_status'  => $response_status,
            'response_code'    => $response_code,
            'response_message' => $response_message,
            'raw_response'     => json_encode($data),
            'updated'          => \Drupal::time()->getRequestTime(),
          ])
          ->condition('id', $transaction['id'])
          ->execute();

        if ($payment_status === 'approved') {
          $booking = \Drupal::entityTypeManager()->getStorage('booking')->load($booking_id);
          if ($booking && $booking->get('field_status')->getString() !== 'payment_confirmed') {
            $booking->set('field_status', 'payment_confirmed');
            $booking->save();
            \Drupal::service('sr.services')->emailBooking($booking_id);
          }
        }

        \Drupal::messenger()->addStatus($this->t('Live status from PayTabs: @status — @msg', [
          '@status' => strtoupper($payment_status),
          '@msg'    => $response_message,
        ]));
      }
    }
    else {
      \Drupal::messenger()->addWarning($this->t('Could not reach PayTabs to fetch live status.'));
    }

    return $this->redirect('sr_paytabs.payment_view', ['booking_id' => $booking_id]);
  }

  /**
   * Maps PayTabs response_status to booking field_status.
   * A=Approved, D=Declined, E=Error, H=Hold, P=Pending, V=Void
   */
  protected function mapResponseStatus($response_status) {
    switch ($response_status) {
      case 'A': return 'payment_confirmed';
      case 'P':
      case 'H': return 'payment_bank_pending';
      default:  return 'payment_failed';
    }
  }

}
