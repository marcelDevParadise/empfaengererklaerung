<?php if (!defined('ABSPATH')) { exit; }
$field = static function (string $key, string $type = 'text', string $hint = ''): void {
    [$label, $limit, $required] = EE_Validation::FIELDS[$key];
    if ($key === 'received_date') { $required = true; }
    $autocomplete = ['first_name' => 'given-name', 'last_name' => 'family-name', 'email' => 'email', 'phone' => 'tel', 'address' => 'street-address'];
    ?>
    <div class="ee-field" data-field="<?= esc_attr($key) ?>">
        <label for="ee-<?= esc_attr($key) ?>"><?= esc_html($label) ?><?= $required ? '' : ' <span>(optional)</span>' ?></label>
        <?php if ($type === 'textarea'): ?>
            <textarea id="ee-<?= esc_attr($key) ?>" name="<?= esc_attr($key) ?>" rows="<?= $key === 'contents' ? '3' : '2' ?>" maxlength="<?= (int) $limit ?>" <?= $required ? 'required' : '' ?> aria-describedby="ee-<?= esc_attr($key) ?>-hint ee-<?= esc_attr($key) ?>-error" autocomplete="<?= esc_attr($autocomplete[$key] ?? 'off') ?>"></textarea>
        <?php else: ?>
            <input id="ee-<?= esc_attr($key) ?>" name="<?= esc_attr($key) ?>" type="<?= esc_attr($type) ?>" maxlength="<?= (int) $limit ?>" <?= $key === 'tracking' ? 'inputmode="numeric" pattern="' . esc_attr(EE_Validation::TRACKING_PREFIX) . '[0-9]+" title="Nur Ziffern, beginnend mit ' . esc_attr(EE_Validation::TRACKING_PREFIX) . '"' : '' ?> <?= $required ? 'required' : '' ?> autocomplete="<?= esc_attr($autocomplete[$key] ?? 'off') ?>" aria-describedby="ee-<?= esc_attr($key) ?>-hint ee-<?= esc_attr($key) ?>-error">
        <?php endif; ?>
        <small id="ee-<?= esc_attr($key) ?>-hint"><?= esc_html($hint) ?></small>
        <small class="ee-error" id="ee-<?= esc_attr($key) ?>-error"></small>
    </div>
    <?php
};
?>
<section class="ee-app" data-config="<?= esc_attr(wp_json_encode($config)) ?>" style="--ee-accent:<?= esc_attr($config['accent']) ?>" aria-label="Empfängererklärung">
    <div class="ee-heading"><span class="ee-eyebrow">SENDUNG KLÄREN</span><h2>Deine Empfängererklärung</h2><p>Trage die Angaben zu deiner Sendung ein und unterschreibe direkt hier. Deine Erklärung erhältst du anschließend als PDF.</p></div>
    <ol class="ee-progress" aria-label="Formularschritte"><li aria-current="step"><span>1</span>Sendung</li><li><span>2</span>Erklärung</li><li><span>3</span>Prüfen &amp; unterschreiben</li></ol>
    <noscript><p>Bitte JavaScript aktivieren, um das Formular auszufüllen und zu unterschreiben.</p></noscript>
    <div class="ee-status" role="status" aria-live="polite">Formular wird geladen …</div>
    <form class="ee-form" novalidate hidden>
        <div class="ee-trap" aria-hidden="true"><label>Website<input name="website" type="text" tabindex="-1" autocomplete="off"></label></div>
        <div class="ee-panel" data-step="0">
            <h3 tabindex="-1">Welche Sendung betrifft es?</h3><p class="ee-muted">Die Sendungsnummer findest du in der Versandbestätigung. Alle Felder ohne „optional“ sind erforderlich.</p>
            <div class="ee-grid"><?php $field('tracking', 'text', 'Nur die vollständige Sendungsnummer eingeben, ohne Buchstaben oder Leerzeichen. Sie beginnt mit ' . EE_Validation::TRACKING_PREFIX . '.'); $field('carrier', 'text', 'Zum Beispiel DHL, Deutsche Post, DPD oder Hermes.'); ?></div>
            <div class="ee-grid"><div class="ee-field" data-field="sender"><label for="ee-sender">Absender mit Anschrift</label><textarea id="ee-sender" name="sender" rows="3" readonly aria-describedby="ee-sender-hint"><?= esc_textarea(EE_Validation::SENDER_ADDRESS) ?></textarea><small id="ee-sender-hint">Der Absender ist bereits hinterlegt.</small></div><?php $field('recipient', 'textarea', 'Name und Anschrift, an die die Sendung adressiert war.'); ?></div>
            <?php $field('contents', 'textarea', 'Bitte die Artikel oder den Inhalt kurz beschreiben.'); ?>
            <details class="ee-details"><summary>Angaben aus der Zustellbestätigung ergänzen <span>(optional)</span></summary><div class="ee-grid"><?php $field('delivery_to'); $field('delivery_date', 'date'); ?></div></details>
        </div>
        <div class="ee-panel" data-step="1" hidden>
            <h3 tabindex="-1">Was ist mit der Sendung passiert?</h3>
            <fieldset class="ee-choices" data-field="receipt"><legend>Erhalt der Sendung</legend>
                <label><input type="radio" name="receipt" value="not_received" required> Ich habe die Sendung nicht erhalten.</label>
                <label><input type="radio" name="receipt" value="received" required> Ich habe die Sendung erhalten.</label>
                <small class="ee-error" id="ee-receipt-error"></small>
            </fieldset>
            <div data-condition="received" hidden><?php $field('received_date', 'date'); ?></div>
            <div data-condition="not_received" hidden><label class="ee-check"><input type="checkbox" name="unknown"> Über den Verbleib der Sendung ist mir/uns nichts bekannt.</label></div>
            <fieldset class="ee-choices" data-field="cod"><legend>War es eine Nachnahmesendung?</legend><div class="ee-inline"><label><input type="radio" name="cod" value="no" checked> Nein</label><label><input type="radio" name="cod" value="yes"> Ja</label></div><small class="ee-error" id="ee-cod-error"></small></fieldset>
            <fieldset class="ee-choices" data-field="cod_payment" data-condition="cod" hidden><legend>Wie wurde der Nachnahmebetrag bezahlt?</legend>
                <label><input type="radio" name="cod_payment" value="courier"> An den Zusteller gezahlt</label>
                <label><input type="radio" name="cod_payment" value="branch"> Am Ausgabeschalter der Postfiliale/Postagentur gezahlt</label>
                <label><input type="radio" name="cod_payment" value="unpaid"> Nicht gezahlt</label>
                <small class="ee-error" id="ee-cod_payment-error"></small>
            </fieldset>
            <h3>Wer gibt die Erklärung ab?</h3>
            <div class="ee-grid"><?php $field('first_name'); $field('last_name'); ?></div>
            <?php $field('address', 'textarea'); ?>
            <div class="ee-grid"><?php $field('email', 'email', 'Hierhin senden wir die unterschriebene PDF-Kopie.'); $field('phone', 'tel'); ?></div>
            <fieldset class="ee-choices" data-field="person"><legend>Ich erkläre für</legend><div class="ee-inline"><label><input type="radio" name="person" value="ich" checked> mich („ich“)</label><label><input type="radio" name="person" value="wir"> uns als Empfänger („wir“)</label></div><small class="ee-error" id="ee-person-error"></small></fieldset>
        </div>
        <div class="ee-panel" data-step="2" hidden>
            <h3 tabindex="-1">Bitte prüfe deine Angaben</h3><p class="ee-muted">Mit „Zurück“ kannst du Angaben korrigieren, bevor du unterschreibst.</p>
            <div class="ee-review"></div>
            <div class="ee-grid"><?php $field('place'); $field('date', 'date'); ?></div>
            <div data-field="confirmed"><label class="ee-check"><input type="checkbox" name="confirmed" required> Ich versichere/Wir versichern, dass die vorstehenden Angaben richtig und vollständig sind.</label><small class="ee-error" id="ee-confirmed-error"></small></div>
            <div class="ee-signature" data-field="signature"><div class="ee-signature-heading"><label for="ee-signature">Deine Unterschrift</label><button type="button" class="ee-link" data-clear>Zurücksetzen</button></div>
                <p class="ee-muted" id="ee-signature-help">Unterschreibe mit dem Finger, einem Stift oder der Maus im Feld.</p>
                <canvas id="ee-signature" width="1200" height="400" aria-label="Unterschrift zeichnen" aria-describedby="ee-signature-help ee-signature-error"></canvas>
                <small class="ee-error" id="ee-signature-error"></small><small class="ee-signature-note" aria-live="polite"></small>
            </div>
            <p class="ee-privacy">Deine Angaben und die unterschriebene Erklärung werden zur Bearbeitung an <?= esc_html($settings['company']) ?> übermittelt und gespeichert. Du erhältst eine Kopie per E-Mail.<?php if (get_privacy_policy_url()): ?> <a href="<?= esc_url(get_privacy_policy_url()) ?>" target="_blank" rel="noopener noreferrer">Datenschutzhinweise</a><?php endif; ?></p>
        </div>
        <div class="ee-actions"><button type="button" class="ee-secondary" data-back hidden>Zurück</button><button type="button" class="ee-primary" data-next>Weiter <span aria-hidden="true">→</span></button><button type="submit" class="ee-primary" data-submit hidden>Unterschreiben &amp; absenden</button></div>
    </form>
    <div class="ee-success" hidden tabindex="-1"><div class="ee-success-icon" aria-hidden="true">✓</div><h3>Deine Erklärung ist gespeichert.</h3><p class="ee-reference"></p><p class="ee-mail-message"></p><a class="ee-primary ee-download" rel="noreferrer">PDF herunterladen</a><p class="ee-muted ee-expiry"></p></div>
</section>
