<?php

declare(strict_types=1);

namespace Drupal\propertyfile\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\propertyfile\PropertyfileInterface;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the propertyfile entity class.
 *
 * @ContentEntityType(
 *   id = "propertyfile",
 *   label = @Translation("Propertyfile"),
 *   label_collection = @Translation("Propertyfiles"),
 *   label_singular = @Translation("propertyfile"),
 *   label_plural = @Translation("propertyfiles"),
 *   label_count = @PluralTranslation(
 *     singular = "@count propertyfiles",
 *     plural = "@count propertyfiles",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\propertyfile\PropertyfileListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\propertyfile\Form\PropertyfileForm",
 *       "edit" = "Drupal\propertyfile\Form\PropertyfileForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "propertyfile",
 *   admin_permission = "administer propertyfile",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/propertyfile",
 *     "add-form" = "/propertyfile/add",
 *     "canonical" = "/propertyfile/{propertyfile}",
 *     "edit-form" = "/propertyfile/{propertyfile}/edit",
 *     "delete-form" = "/propertyfile/{propertyfile}/delete",
 *     "delete-multiple-form" = "/admin/content/propertyfile/delete-multiple",
 *   },
 *   field_ui_base_route = "entity.propertyfile.settings",
 * )
 */
final class Propertyfile extends ContentEntityBase implements PropertyfileInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {

    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Label'))
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setDescription(t('The time that the propertyfile was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time that the propertyfile was last edited.'));

    $fields['file'] = BaseFieldDefinition::create('file')
      ->setLabel(t('File'))
      ->setSettings([
        'uri_scheme' => 'private',
        'file_extensions' => 'csv',
        'description_field' => FALSE,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['vendor_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Vendor ID'))
      ->setDescription(t('An integer identifier for the vendor.'))
      ->setSettings([
        'min' => 0, // Optional: enforce only positive integers
        'max' => 999999, // Optional: set a reasonable upper bound
      ])
      ->setDisplayOptions('form', [
        'type' => 'number',
        'weight' => -5,
        'settings' => [
          'placeholder' => '',
        ],
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'number_integer',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['file_status'] = BaseFieldDefinition::create('string')
      ->setLabel(t('File Status'))
      ->setSettings(['max_length' => 64])
      ->setSetting('max_length', 255)
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'string',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['error'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Error'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['info'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Info'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
