<?php

declare(strict_types=1);

namespace Drupal\propertyvendor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a propertyvendor entity type.
 */
interface PropertyvendorInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
