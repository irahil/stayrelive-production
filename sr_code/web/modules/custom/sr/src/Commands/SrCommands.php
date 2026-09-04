<?php

namespace Drupal\sr\Commands;

use Drush\Commands\DrushCommands;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use Drupal\taxonomy\Entity\Term;
use Drupal\sr\Rategain;

class SrCommands extends DrushCommands
{

  /**
   * Sync RateGain destinations to town_city taxonomy.
   *
   * @command sr:sync-rg-destinations
   * @aliases sr-sync-rg-dest
   * @usage drush sr:sync-rg-destinations
   *   Fetches destinations from RateGain and syncs to town_city taxonomy.
   */
  public function syncRG_Destinations()
  {

    $this->output()->writeln('Fetching destinations from RateGain...');

    // --- 1. Call RateGain API ---
    $destinations = Rategain::fetchDestinations();

    if (empty($destinations)) {
      $this->logger()->warning('No destinations returned from API.');
      return;
    }

    $this->output()->writeln('Total destinations received: ' . count($destinations));

    // --- 2. Build a name|country -> tid lookup WITHOUT loading full entities ---
    // The previous version loaded all ~71k town_city terms as full entities
    // and then, for every one of ~19k API destinations, linearly scanned all
    // of them (up to ~1.4 billion field accesses through the entity API) —
    // that's what was exhausting PHP's memory limit and crashing before
    // updating anything. A single lightweight DB query + hash-map lookup
    // does the same matching in O(n+m) instead of O(n*m).
    $rows = \Drupal::database()->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid', 'name'])
      ->condition('t.vid', 'town_city')
      ->execute()
      ->fetchAllKeyed(0, 1);

    $country_by_tid = \Drupal::database()->select('taxonomy_term__field_country_code', 'c')
      ->fields('c', ['entity_id', 'field_country_code_value'])
      ->execute()
      ->fetchAllKeyed();

    $lookup = [];
    foreach ($rows as $tid => $name) {
      $key = strtolower(ucwords(strtolower(trim($name)))) . '|' . strtolower(trim($country_by_tid[$tid] ?? ''));
      $lookup[$key] = $tid;
    }

    // --- 3. Process each destination ---
    $created = 0;
    $updated = 0;
    $skipped = 0;
    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $processed_since_reset = 0;

    foreach ($destinations as $dest) {

      $dest_code = trim($dest['destCode'] ?? $dest['DestCode'] ?? '');
      $dest_name = trim($dest['destName'] ?? $dest['DestName'] ?? '');
      $country_code = trim($dest['countryCode'] ?? $dest['CountryCode'] ?? '');
      $country_name = trim($dest['countryName'] ?? $dest['CountryName'] ?? '');

      if (empty($dest_code) || empty($dest_name) || empty($country_code)) {
        $skipped++;
        continue;
      }

      // ------------------------------------
      // Normalize API city name.
      // ------------------------------------
      $city_name = trim(str_ireplace($country_name, '', $dest_name));
      $city_name = trim($city_name);

      // If empty after removing country, fallback to destName
      if (empty($city_name)) {
        $this->output()->writeln("[SKIPPED] Empty city for destCode: {$dest_code}");
        $skipped++;
        continue;
      }

      $city_name = ucwords(strtolower($city_name));

      // ------------------------------------
      // Look up existing taxonomy by city name + country (O(1) hash lookup,
      // same matching rule as before: name must match; country only has to
      // match when the existing term actually has one set).
      // ------------------------------------
      $lookup_key = strtolower($city_name) . '|' . strtolower($country_code);
      $tid = $lookup[$lookup_key] ?? ($lookup[strtolower($city_name) . '|'] ?? null);

      // ------------------------------------
      // Existing city -> Update
      // ------------------------------------
      if ($tid) {
        $term = $term_storage->load($tid);
        $needs_save = FALSE;

        if (
          $term->hasField('field_dest_code')
          && $term->get('field_dest_code')->value != $dest_code
        ) {

          $term->set('field_dest_code', $dest_code);
          $needs_save = TRUE;
        }

        if (
          $term->hasField('field_country_code')
          && $term->get('field_country_code')->value != $country_code
        ) {

          $term->set('field_country_code', $country_code);
          $needs_save = TRUE;
        }

        if ($needs_save) {

          $term->save();

          $this->output()->writeln(
            "[UPDATED] {$city_name} (Dest: {$dest_code}, Country: {$country_code})"
          );

          $updated++;
        } else {
          $skipped++;
        }

        // Entities loaded via the storage handler accumulate in its static
        // cache for the rest of the request — reset periodically so memory
        // doesn't creep back up over ~19k iterations.
        if (++$processed_since_reset >= 200) {
          $term_storage->resetCache();
          $processed_since_reset = 0;
        }

        continue;
      }

      // ------------------------------------
      // City not found -> Create
      // ------------------------------------
      $new_term = Term::create([
        'vid' => 'town_city',
        'name' => $city_name,
      ]);

      if ($new_term->hasField('field_dest_code')) {
        $new_term->set('field_dest_code', $dest_code);
      }

      if ($new_term->hasField('field_country_code')) {
        $new_term->set('field_country_code', $country_code);
      }

      $new_term->save();

      $lookup[$lookup_key] = $new_term->id();

      $this->output()->writeln(
        "[CREATED] {$city_name} (Dest: {$dest_code}, Country: {$country_code})"
      );

      $created++;
    }

    // --- 4. Summary ---
    $this->output()->writeln('');
    $this->output()->writeln('---- Sync Complete ----');
    $this->output()->writeln("Created : {$created}");
    $this->output()->writeln("Updated : {$updated}");
    $this->output()->writeln("Skipped : {$skipped}");
  }


  /**
   * Cleanup ALL RateGain imported cities.
   *
   * @command sr:cleanup-rg-destinations
   * @aliases sr-cleanup-rg
   */
  public function cleanupRG_Destinations() {
    $this->output()->writeln('Deleting all RateGain imported cities...');

    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    $tids = \Drupal::entityQuery('taxonomy_term')
      ->condition('vid', 'town_city')
      ->exists('field_dest_code') // IMPORTANT
      ->accessCheck(FALSE)
      ->execute();

    $chunks = array_chunk($tids, 100);
    $deleted = 0;

    foreach ($chunks as $chunk) {
      $terms = $term_storage->loadMultiple($chunk);

      foreach ($terms as $term) {
        $dest_code = $term->get('field_dest_code')->value ?? '';

        // Only delete API terms
        if (!empty($dest_code)) {

          try {
            $this->output()->writeln("[DELETING] " . $term->getName());
            $term->delete();
            $deleted++;
          } catch (\Exception $e) {
            $this->output()->writeln("[ERROR] " . $term->getName());
          }
        }
      }

      // Free memory
      $term_storage->resetCache($chunk);
      unset($terms);
      gc_collect_cycles();
    }

    $this->output()->writeln('');
    $this->output()->writeln('---- Cleanup Complete ----');
    $this->output()->writeln("Deleted : {$deleted}");
  }

  /**
   * Fix town_city terms that are name-variant duplicates of another term
   * (e.g. "Ixtapa - Zihuatanejo" / "Ixtapa - Zihuatanejo Mexico") and so
   * were never reachable by sr:sync-rg-destinations, which only updates
   * terms whose name matches a RateGain destName exactly. For each such
   * pair, whichever term's name+country matches RateGain's live list is
   * treated as correct, and its dest/country code is copied onto the
   * other. Terms are never deleted, since other content may reference
   * their tid.
   *
   * @command sr:fix-duplicate-dest-codes
   * @aliases sr-fix-dup-dest
   * @option dry-run Report what would change without saving anything.
   * @usage drush sr:fix-duplicate-dest-codes --dry-run
   *   Preview fixes without saving.
   * @usage drush sr:fix-duplicate-dest-codes
   *   Apply fixes.
   */
  public function fixDuplicateDestCodes($options = ['dry-run' => FALSE])
  {
    $dry_run = !empty($options['dry-run']);

    $this->output()->writeln('Fetching destinations from RateGain...');
    $destinations = Rategain::fetchDestinations();

    if (empty($destinations)) {
      $this->logger()->warning('No destinations returned from API.');
      return;
    }

    // Same name|country -> destCode normalization as sr:sync-rg-destinations,
    // so "correct" here means the same thing that command would write.
    $rg_lookup = [];
    foreach ($destinations as $dest) {
      $dest_code = trim($dest['destCode'] ?? $dest['DestCode'] ?? '');
      $dest_name = trim($dest['destName'] ?? $dest['DestName'] ?? '');
      $country_code = trim($dest['countryCode'] ?? $dest['CountryCode'] ?? '');
      $country_name = trim($dest['countryName'] ?? $dest['CountryName'] ?? '');

      if (empty($dest_code) || empty($dest_name) || empty($country_code)) {
        continue;
      }

      $city_name = trim(str_ireplace($country_name, '', $dest_name));
      if (empty($city_name)) {
        continue;
      }

      $key = strtolower($city_name) . '|' . strtolower($country_code);
      $rg_lookup[$key] = $dest_code;
    }

    $this->output()->writeln('RateGain destinations indexed: ' . count($rg_lookup));

    // Find candidate duplicate pairs in the DB directly (one name a prefix
    // of the other) rather than comparing all terms pairwise in PHP — with
    // ~71k town_city terms an in-PHP O(n^2) scan is the same mistake that
    // used to crash sr:sync-rg-destinations.
    $query = \Drupal::database()->query("
      SELECT
        t1.tid AS tid1, t1.name AS name1, dc1.field_dest_code_value AS dest1, cc1.field_country_code_value AS country1,
        t2.tid AS tid2, t2.name AS name2, dc2.field_dest_code_value AS dest2, cc2.field_country_code_value AS country2
      FROM {taxonomy_term_field_data} t1
      JOIN {taxonomy_term_field_data} t2
        ON t2.vid = t1.vid
        AND t1.tid <> t2.tid
        AND t2.name LIKE CONCAT(t1.name, '%')
        AND t2.name <> t1.name
        -- Require a word boundary right after the prefix, so 'Ixtapa' does
        -- not falsely match 'Ixtapan De La Sal' (a different place that
        -- merely starts with the same characters).
        AND NOT SUBSTRING(t2.name, CHAR_LENGTH(t1.name) + 1, 1) REGEXP '[[:alnum:]]'
      LEFT JOIN {taxonomy_term__field_dest_code} dc1 ON dc1.entity_id = t1.tid
      LEFT JOIN {taxonomy_term__field_dest_code} dc2 ON dc2.entity_id = t2.tid
      LEFT JOIN {taxonomy_term__field_country_code} cc1 ON cc1.entity_id = t1.tid
      LEFT JOIN {taxonomy_term__field_country_code} cc2 ON cc2.entity_id = t2.tid
      WHERE t1.vid = 'town_city'
        AND IFNULL(dc1.field_dest_code_value, '') <> IFNULL(dc2.field_dest_code_value, '')
    ");

    $pairs = $query->fetchAll();
    $this->output()->writeln('Candidate duplicate pairs found: ' . count($pairs));

    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $fixed = 0;
    $no_match = 0;

    foreach ($pairs as $row) {
      $a = ['tid' => $row->tid1, 'name' => $row->name1, 'dest' => $row->dest1 ?? '', 'country' => $row->country1 ?? ''];
      $b = ['tid' => $row->tid2, 'name' => $row->name2, 'dest' => $row->dest2 ?? '', 'country' => $row->country2 ?? ''];

      $key_a = strtolower(trim($a['name'])) . '|' . strtolower(trim($a['country']));
      $key_b = strtolower(trim($b['name'])) . '|' . strtolower(trim($b['country']));

      $rg_code_a = $rg_lookup[$key_a] ?? NULL;
      $rg_code_b = $rg_lookup[$key_b] ?? NULL;

      if ($rg_code_a !== NULL && $rg_code_a === $a['dest']) {
        $source = $a;
        $target = $b;
      }
      elseif ($rg_code_b !== NULL && $rg_code_b === $b['dest']) {
        $source = $b;
        $target = $a;
      }
      else {
        $this->output()->writeln(
          "[NO MATCH] \"{$a['name']}\" (tid {$a['tid']}, dest={$a['dest']}) vs " .
          "\"{$b['name']}\" (tid {$b['tid']}, dest={$b['dest']}) — neither matches RateGain's live list, skipping."
        );
        $no_match++;
        continue;
      }

      $this->output()->writeln(
        ($dry_run ? '[WOULD FIX] ' : '[FIXING] ') .
        "\"{$target['name']}\" (tid {$target['tid']}): dest_code \"{$target['dest']}\" -> \"{$source['dest']}\", " .
        "country_code \"{$target['country']}\" -> \"{$source['country']}\" " .
        "[source: \"{$source['name']}\" tid {$source['tid']}]"
      );

      if (!$dry_run) {
        $term = $term_storage->load($target['tid']);
        if ($term) {
          $old_dest = $target['dest'];
          $old_country = $target['country'];
          $term->set('field_dest_code', $source['dest']);
          $term->set('field_country_code', $source['country']);
          $term->save();

          \Drupal::logger('sr')->notice(
            'sr:fix-duplicate-dest-codes: term @tid (@name) dest_code "@old_dest" -> "@new_dest", country_code "@old_country" -> "@new_country" (source: term @source_tid, @source_name)',
            [
              '@tid' => $target['tid'],
              '@name' => $target['name'],
              '@old_dest' => $old_dest,
              '@new_dest' => $source['dest'],
              '@old_country' => $old_country,
              '@new_country' => $source['country'],
              '@source_tid' => $source['tid'],
              '@source_name' => $source['name'],
            ]
          );
        }
      }

      $fixed++;
    }

    $this->output()->writeln('');
    $this->output()->writeln('---- ' . ($dry_run ? 'Dry Run' : 'Fix') . ' Complete ----');
    $this->output()->writeln("Fixed (or would fix) : {$fixed}");
    $this->output()->writeln("No RateGain match     : {$no_match}");
  }

}