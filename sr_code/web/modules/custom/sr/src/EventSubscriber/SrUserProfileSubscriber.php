<?php

namespace Drupal\sr\EventSubscriber;

use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Drupal\common_utilities\Utilities\commonUtil;

/**
 * Event Subscriber SrUserProfileSubscriber.
 */
class SrUserProfileSubscriber implements EventSubscriberInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $currentRouteMatch;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * MyEventSubscriber constructor.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Routing\CurrentRouteMatch $current_route_match
   *   The current route match.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(RequestStack $request_stack, CurrentRouteMatch $current_route_match, AccountInterface $current_user) {
    $this->requestStack = $request_stack;
    $this->currentRouteMatch = $current_route_match;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events = [
      KernelEvents::REQUEST => 'redirectAuthUsersOnProfile',
    ];
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function redirectAuthUsersOnProfile(RequestEvent $event) {
    $routeName = $this->currentRouteMatch->getRouteName();

    // Stop user to access /user/UID
    if ($routeName === 'entity.user.canonical') {
      $current_path = commonUtil::my_generate_url("/");
      $response = new TrustedRedirectResponse($current_path, 302);
      $event->setResponse($response);
    }
    // Stop non admin user to access /user/UID/edit
    if ($routeName === 'entity.user.edit_form') {
      $current_roles = $this->currentUser->getRoles();
      if (!in_array('sr_admin', $current_roles) && !in_array('administrator', $current_roles) && !in_array('authenticated', $current_roles)) {
        $current_path = commonUtil::my_generate_url("/");
        $response = new TrustedRedirectResponse($current_path, 302);
        $event->setResponse($response);
      }
    }
  }

}