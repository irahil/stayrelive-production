<?php declare(strict_types = 1);

namespace Drupal\booking;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a booking entity type.
 */
interface BookingInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
