<?php

namespace Drupal\sr\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Event subscriber to set the country code during the request.
 */
class SrAgentLoginDisplay implements EventSubscriberInterface {

  protected $requestStack;

  /**
   * Constructs the event subscriber.
   */
  public function __construct(RequestStack $requestStack) {
    $this->requestStack = $requestStack;
  }

 /**
   * Sets the country code in the session based on the user's IP address.
   */
  public function onKernelRequest(RequestEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }
    $request = $this->requestStack->getCurrentRequest();
    $session = $request->getSession();
  
    // Start the session if it hasn't been started yet.
    if (!$session->isStarted()) {
      $session->start();
    }
    $ip_address = $request->getClientIp();
    

    // Skip geolocation for private/local IPs (dev environments).
    if (filter_var($ip_address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === FALSE) {
      $session->set('country_code', 'testing');
      return;
    }

    try {
      $geolocation_data = @file_get_contents("http://ip-api.com/json/$ip_address");
      if ($geolocation_data === FALSE) {
        $session->set('country_code', 'testing');
        return;
      }
      $geo_info = json_decode($geolocation_data, TRUE);
      $country_code = isset($geo_info['countryCode']) ? strtolower($geo_info['countryCode']) : 'testing';
      $session->set('country_code', $country_code);
    }
    catch (\Exception $e) {
      \Drupal::logger('sr')->error('Failed to fetch country code: ' . $e->getMessage());
      $session->set('country_code', 'testing');
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onKernelRequest'];
    return $events;
  }
}