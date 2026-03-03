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
    try {
      $geolocation_data = file_get_contents("http://ip-api.com/json/$ip_address");
      $geo_info = json_decode($geolocation_data, TRUE);
      $country_code = isset($geo_info['countryCode']) ? strtolower($geo_info['countryCode']) : 'testing';
      $session->set('country_code', $country_code);
    }
    catch (\Exception $e) {
      \Drupal::logger('sr')->error('Failed to fetch country code: ' . $e->getMessage());
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