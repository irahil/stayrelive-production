<?php

declare(strict_types=1);

namespace Drupal\propertyfile;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a propertyfile entity type.
 */
interface PropertyfileInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
