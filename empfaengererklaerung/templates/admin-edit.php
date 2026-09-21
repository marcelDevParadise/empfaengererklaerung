<?php if (!defined('ABSPATH')) { exit; }
$detail_url = add_query_arg(['page' => 'ee-archive', 'view' => $id], admin_url('admin.php'));
$errors = $error ? ($error->get_error_data()['fields'] ?? []) : [];
$value = static function ($key) use ($data): string { return is_string($data[$key] ?? null) ? $data[$key] : ''; };
?>
<div class="wrap ee-admin"><h1>Angaben bearbeiten · <?= esc_html($row->reference) ?></h1>
<p>Speichere korrigierte Angaben als überarbeitete Fassung mit eigenem PDF. Das unterschriebene Original bleibt erhalten. <?= !empty($stored['_signature']) ? 'Die Originalunterschrift wird mit einem Hinweis auf die Überarbeitung übernommen.' : 'Bei älteren Erklärungen ist die Unterschrift nur im Original-PDF vorhanden und kann nicht automatisch übernommen werden.' ?> Es wird keine E-Mail versendet. Die letzte überarbeitete Fassung wird beim erneuten Speichern ersetzt.</p>
<?php if ($error): ?><div class="notice notice-error"><p><?= esc_html($error->get_error_message()) ?></p><?php foreach ($errors as $message): ?><p><?= esc_html($message) ?></p><?php endforeach; ?></div><?php endif; ?>
<form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>">
<?php wp_nonce_field('ee_archive_action'); ?><input type="hidden" name="action" value="ee_archive_action"><input type="hidden" name="operation" value="edit"><input type="hidden" name="id" value="<?= (int) $id ?>"><input type="hidden" name="revision" value="<?= esc_attr($revision) ?>">
<table class="form-table" role="presentation"><tbody>
<?php foreach (EE_Validation::FIELDS as $key => [$label, $limit, $required]): ?>
<tr><th><label for="ee-edit-<?= esc_attr($key) ?>"><?= esc_html($label) ?></label></th><td>
<?php if (in_array($key, ['sender', 'recipient', 'address', 'contents'], true)): ?>
<textarea class="large-text" rows="3" id="ee-edit-<?= esc_attr($key) ?>" name="declaration[<?= esc_attr($key) ?>]" maxlength="<?= (int) $limit ?>" <?= $required ? 'required' : '' ?> <?= $key === 'sender' ? 'readonly' : '' ?>><?= esc_textarea($key === 'sender' ? EE_Validation::SENDER_ADDRESS : $value($key)) ?></textarea>
<?php else: $type = substr($key, -4) === 'date' ? 'date' : ($key === 'email' ? 'email' : 'text'); ?>
<input class="regular-text" id="ee-edit-<?= esc_attr($key) ?>" name="declaration[<?= esc_attr($key) ?>]" type="<?= esc_attr($type) ?>" value="<?= esc_attr($value($key)) ?>" maxlength="<?= (int) $limit ?>" <?= $required ? 'required' : '' ?> <?= $type === 'date' ? 'max="' . esc_attr(current_time('Y-m-d')) . '"' : '' ?> <?= $key === 'tracking' ? 'inputmode="numeric" pattern="' . esc_attr(EE_Validation::TRACKING_PREFIX) . '[0-9]+"' : '' ?>>
<?php endif; ?>
<?php if ($key === 'tracking'): ?><p class="description">Nur Ziffern, beginnend mit <?= esc_html(EE_Validation::TRACKING_PREFIX) ?>. Vollständige Nummer einschließlich führender Nullen eingeben.</p><?php endif; ?>
<?php if ($key === 'received_date'): ?><p class="description">Erforderlich, wenn die Sendung erhalten wurde.</p><?php endif; ?>
</td></tr>
<?php endforeach; ?>
<?php foreach ([
    'person' => ['Erklärung für', ['ich' => 'Mich (ich)', 'wir' => 'Uns als Empfänger (wir)']],
    'receipt' => ['Erhalt der Sendung', ['not_received' => 'Nicht erhalten', 'received' => 'Erhalten']],
    'cod' => ['Nachnahmesendung', ['no' => 'Nein', 'yes' => 'Ja']],
    'cod_payment' => ['Nachnahmezahlung', ['' => 'Keine Angabe / keine Nachnahme', 'courier' => 'An den Zusteller gezahlt', 'branch' => 'In der Postfiliale/Postagentur gezahlt', 'unpaid' => 'Nicht gezahlt']],
] as $key => [$label, $options]): ?>
<tr><th><label for="ee-edit-<?= esc_attr($key) ?>"><?= esc_html($label) ?></label></th><td><select id="ee-edit-<?= esc_attr($key) ?>" name="declaration[<?= esc_attr($key) ?>]">
<?php foreach ($options as $option => $text): ?><option value="<?= esc_attr($option) ?>" <?php selected($value($key), $option); ?>><?= esc_html($text) ?></option><?php endforeach; ?>
</select></td></tr>
<?php endforeach; ?>
<tr><th>Verbleib</th><td><label><input type="checkbox" name="declaration[unknown]" value="1" <?php checked(!empty($data['unknown'])); ?>> Über den Verbleib ist nichts bekannt (nur bei „nicht erhalten“).</label></td></tr>
</tbody></table>
<?php submit_button('Überarbeitung speichern'); ?><p><a href="<?= esc_url($detail_url) ?>">Abbrechen und zur Detailansicht</a></p>
</form></div>
