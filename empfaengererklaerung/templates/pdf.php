<?php if (!defined('ABSPATH')) { exit; }
$e = static fn($v) => esc_html((string) $v);
$cell = static fn($v) => nl2br(esc_html((string) $v));
$accent = sanitize_hex_color($brand['accent']) ?: '#205c50';
?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><style>
@page { margin: 40pt 42pt 48pt; }
body { font-family: 'DejaVu Sans', sans-serif; color: #1e2933; font-size: 10pt; line-height: 1.3; }
.header { border-bottom: 3pt solid <?= $accent ?>; padding-bottom: 10pt; margin-bottom: 12pt; }
.logo { float: right; max-width: 110pt; max-height: 32pt; }
.company { font-size: 10pt; color: <?= $accent ?>; font-weight: bold; }
h1 { font-size: 23pt; font-weight: normal; margin: 7pt 0; }
.muted { color: #62717b; font-size: 8pt; }
h2 { font-size: 10pt; color: <?= $accent ?>; margin: 13pt 0 6pt; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
td { vertical-align: top; padding: 4pt 8pt; border-bottom: 1pt solid #e6e9eb; overflow-wrap: break-word; word-wrap: break-word; }
td.label { width: 31%; color: #53636d; font-size: 9pt; }
.declaration { page-break-inside: avoid; margin-top: 14pt; padding: 12pt; border: 1pt solid #dce4e1; background: #f6f9f8; }
.declaration h2 { margin-top: 0; }
.signature { height: 50pt; max-width: 240pt; }
.signline { border-top: 1pt solid #7e8b90; padding-top: 5pt; margin-top: 2pt; }
p { margin: 7pt 0; }
.content { white-space: pre-wrap; overflow-wrap: break-word; word-wrap: break-word; }
</style></head><body>
<div class="header">
<?php if (!empty($brand['logo'])): ?><img class="logo" src="<?= esc_attr($brand['logo']) ?>" alt=""><?php endif; ?>
<div class="company"><?= $e($brand['company']) ?></div>
<h1>Empfängererklärung</h1>
<?php if (!empty($data['_revised_at'])): ?><p><strong>Überarbeitete Fassung · nicht erneut unterschrieben</strong><br>Überarbeitung <?= (int) $data['_revision_number'] ?> vom <?= $e(get_date_from_gmt($data['_revised_at'], 'd.m.Y H:i')) ?>. Das unterschriebene Original bleibt separat im Archiv erhalten.</p><?php endif; ?>
<div class="muted">Vorgang <?= $e($reference) ?> · Erklärung vom <?= $e(EE_Validation::date($data['date'])) ?></div>
</div>
<h2>01 · Angaben zur Sendung</h2>
<table>
<?php foreach (['tracking' => 'Sendungsnummer', 'carrier' => 'Versanddienstleister', 'sender' => 'Absender', 'recipient' => 'Adressiert an'] as $key => $label): ?>
<tr><td class="label"><?= $e($label) ?></td><td><?= $cell($data[$key]) ?></td></tr>
<?php endforeach; ?>
<?php if ($data['delivery_to']): ?><tr><td class="label">Dokumentierte Zustellung an</td><td><?= $cell($data['delivery_to']) ?></td></tr><?php endif; ?>
<?php if ($data['delivery_date']): ?><tr><td class="label">Dokumentiertes Zustelldatum</td><td><?= $e(EE_Validation::date($data['delivery_date'])) ?></td></tr><?php endif; ?>
</table>
<p class="muted">Sendungsinhalt</p><div class="content"><?= $e($data['contents']) ?></div>
<h2>02 · Erklärende Person</h2>
<table>
<tr><td class="label">Name</td><td><?= $e($data['first_name'] . ' ' . $data['last_name']) ?></td></tr>
<tr><td class="label">Anschrift</td><td><?= $cell($data['address']) ?></td></tr>
<tr><td class="label">E-Mail</td><td><?= $e($data['email']) ?></td></tr>
<?php if ($data['phone']): ?><tr><td class="label">Telefon</td><td><?= $e($data['phone']) ?></td></tr><?php endif; ?>
</table>
<div class="declaration">
<h2>03 · <?= !empty($data['_revised_at']) ? 'Überarbeitete Erklärung' : 'Erklärung und Unterschrift' ?></h2>
<p><?= $e(EE_Validation::statement($data)) ?></p>
<?php if (empty($data['_revised_at'])): ?>
<p><?= $data['person'] === 'wir' ? 'Wir versichern' : 'Ich versichere' ?>, dass die vorstehenden Angaben richtig und vollständig sind.</p>
<?php endif; ?>
<p><?= $e($data['place']) ?>, <?= $e(EE_Validation::date($data['date'])) ?></p>
<?php if (empty($data['_revised_at'])): ?>
<img class="signature" src="<?= esc_attr($data['signature']) ?>" alt="Unterschrift">
<div class="signline"><?= $e($data['first_name'] . ' ' . $data['last_name']) ?><?= $data['person'] === 'wir' ? ' · unterschreibt für die Empfänger' : '' ?></div>
<?php else: ?><p>Diese Angaben wurden im Backend überarbeitet. Für diese Fassung liegt keine neue Unterschrift der erklärenden Person vor.</p><?php endif; ?>
</div>
</body></html>
