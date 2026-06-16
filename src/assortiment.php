<?php
session_start();
$conn = new mysqli("mysql", "root", "password", "nora_flora");

/* ─────────────────────────────────────────────
   Per pagina (whitelist — voorkomt misbruik)
───────────────────────────────────────────── */
$per_pagina_opties = [10, 20, 50, 100];
$per_pagina = (int)($_GET['per_pagina'] ?? 20);
if (!in_array($per_pagina, $per_pagina_opties, true)) $per_pagina = 20;

$pagina = max(1, (int)($_GET['pagina'] ?? 1));

/* ─────────────────────────────────────────────
   Sorteer-whitelist — velden met eigen filter-
   dropdown zijn hier NIET opgenomen
───────────────────────────────────────────── */
$sorteer_opties = [
    'naam_asc'      => ['naam',                 'ASC',  'Naam (A–Z)'],
    'naam_desc'     => ['naam',                 'DESC', 'Naam (Z–A)'],
    'prijs_asc'     => ['verkoopprijs_eur',     'ASC',  'Prijs (laag–hoog)'],
    'prijs_desc'    => ['verkoopprijs_eur',     'DESC', 'Prijs (hoog–laag)'],
    'hoogte_asc'    => ['groeihoogte_cm',       'ASC',  'Hoogte (laag–hoog)'],
    'hoogte_desc'   => ['groeihoogte_cm',       'DESC', 'Hoogte (hoog–laag)'],
    'huisdier_ja'   => ['huisdier_vriendelijk', 'DESC', 'Huisdiervriendelijk eerst'],
    'voorraad_desc' => ['voorraad',             'DESC', 'Voorraad (hoog–laag)'],
    'voorraad_asc'  => ['voorraad',             'ASC',  'Voorraad (laag–hoog)'],
];

$sorteer = $_GET['sorteer'] ?? 'naam_asc';
if (!array_key_exists($sorteer, $sorteer_opties)) $sorteer = 'naam_asc';
[$sort_col, $sort_dir] = $sorteer_opties[$sorteer];
$order_by = "`{$sort_col}` {$sort_dir}";

/* ─────────────────────────────────────────────
   Filterwaarden dynamisch uit de database
───────────────────────────────────────────── */
function distinct(mysqli $conn, string $kolom): array {
    $res  = $conn->query(
        "SELECT DISTINCT `{$kolom}` FROM planten_met_afbeeldingen_zip
         WHERE `{$kolom}` IS NOT NULL AND `{$kolom}` <> ''
         ORDER BY `{$kolom}` ASC"
    );
    $rows = [];
    while ($r = $res->fetch_row()) $rows[] = $r[0];
    return $rows;
}

$standplaatsen  = distinct($conn, 'standplaats');
$waterbehoeftes = distinct($conn, 'waterbehoefte');
$lichtbehoeftes = distinct($conn, 'lichtbehoefte');
$bloeitijden    = distinct($conn, 'bloeitijd');
$kleuren        = distinct($conn, 'kleur');

/* ─────────────────────────────────────────────
   Actieve filters lezen
───────────────────────────────────────────── */
$standplaats_filter   = trim($_GET['standplaats']    ?? '');
$waterbehoefte_filter = trim($_GET['waterbehoefte']  ?? '');
$lichtbehoefte_filter = trim($_GET['lichtbehoefte']  ?? '');
$bloeitijd_filter     = trim($_GET['bloeitijd']      ?? '');
$kleur_filter         = trim($_GET['kleur']          ?? '');
$verberg_uitverkocht  = isset($_GET['verberg_uitverkocht']);

/* ─────────────────────────────────────────────
   WHERE opbouwen
───────────────────────────────────────────── */
$where_parts = [];
$bind_values = [];
$bind_types  = '';

if ($verberg_uitverkocht)        { $where_parts[] = 'voorraad > 0'; }
if ($standplaats_filter   !== '') { $where_parts[] = 'standplaats = ?';   $bind_values[] = $standplaats_filter;   $bind_types .= 's'; }
if ($waterbehoefte_filter !== '') { $where_parts[] = 'waterbehoefte = ?'; $bind_values[] = $waterbehoefte_filter; $bind_types .= 's'; }
if ($lichtbehoefte_filter !== '') { $where_parts[] = 'lichtbehoefte = ?'; $bind_values[] = $lichtbehoefte_filter; $bind_types .= 's'; }
if ($bloeitijd_filter     !== '') { $where_parts[] = 'bloeitijd = ?';     $bind_values[] = $bloeitijd_filter;     $bind_types .= 's'; }
if ($kleur_filter         !== '') { $where_parts[] = 'kleur = ?';         $bind_values[] = $kleur_filter;         $bind_types .= 's'; }

$where = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

/* ─────────────────────────────────────────────
   Totaal tellen
───────────────────────────────────────────── */
$count_sql = "SELECT COUNT(*) FROM planten_met_afbeeldingen_zip {$where}";
if ($bind_values) {
    $cs = $conn->prepare($count_sql);
    $cs->bind_param($bind_types, ...$bind_values);
    $cs->execute();
    $cr = $cs->get_result();
} else {
    $cr = $conn->query($count_sql);
}
$totaal         = $cr ? (int)$cr->fetch_row()[0] : 0;
$totaal_paginas = max(1, (int)ceil($totaal / $per_pagina));
$pagina         = min($pagina, $totaal_paginas);
$offset         = ($pagina - 1) * $per_pagina;

/* ─────────────────────────────────────────────
   Planten ophalen
───────────────────────────────────────────── */
$sql = "
    SELECT naam, verkoopprijs_eur, overview_image, voorraad,
           standplaats, groeihoogte_cm, waterbehoefte,
           lichtbehoefte, bloeitijd, kleur, huisdier_vriendelijk
    FROM planten_met_afbeeldingen_zip
    {$where}
    ORDER BY {$order_by}
    LIMIT ? OFFSET ?
";
$data_vals  = array_merge($bind_values, [$per_pagina, $offset]);
$data_types = $bind_types . 'ii';

$stmt = $conn->prepare($sql);
$stmt->bind_param($data_types, ...$data_vals);
$stmt->execute();
$result = $stmt->get_result();

$plants = [];
while ($row = $result->fetch_assoc()) $plants[] = $row;

/* ─────────────────────────────────────────────
   Paginering-URL: behoudt alle GET-params,
   vervangt alleen het paginanummer
───────────────────────────────────────────── */
function page_url(int $p): string {
    $q = $_GET;
    $q['pagina'] = $p;
    return '?' . http_build_query(array_filter($q, fn($v) => $v !== ''));
}



?>
<!DOCTYPE html>
<html lang="nl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
    <script src="script.js" defer></script>
    <title>Assortiment – Nora's Flora</title>
</head>

<body>
    <header>
        <img src="img/norasFloraLogo.png" class="Logo" loading="lazy" alt="Nora's Flora logo">
        <div class="icon-cart">
            <a href="Shoppingcart.html">
                <svg aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="42.5" height="40"
                     fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd"
                          d="M4 4a1 1 0 0 1 1-1h1.5a1 1 0 0 1 .979.796L7.939 6H19a1 1 0 0 1 .979 1.204l-1.25 6a1 1 0 0 1-.979.796H9.605l.208 1H17a3 3 0 1 1-2.83 2h-2.34a3 3 0 1 1-4.009-1.76L5.686 5H5a1 1 0 0 1-1-1Z"
                          clip-rule="evenodd"/>
                </svg>
                <span>0</span>
            </a>
        </div>
    </header>

    <nav>
        <ul>
            <li><a href="index.html" class="navText">| Home |</a></li>
            <li><a href="assortiment.php" class="navText">| Assortiment |</a></li>
            <li><a href="contact.html" class="navText">| Contact |</a></li>
        </ul>
    </nav>

    <div class="assortment-page">
        <div class="container">

            <!-- ── Filter + Sorteer formulier (GET zodat paginering-links werken) ── -->
            <form method="GET" class="filter-bar">

                <div class="field">
                    <label for="standplaats">Standplaats</label>
                    <select name="standplaats" id="standplaats">
                        <option value="">Alle standplaatsen</option>
                        <?php foreach ($standplaatsen as $sp): ?>
                            <option value="<?= htmlspecialchars($sp) ?>"
                                <?= $standplaats_filter === $sp ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sp) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="waterbehoefte">Waterbehoefte</label>
                    <select name="waterbehoefte" id="waterbehoefte">
                        <option value="">Alle</option>
                        <?php foreach ($waterbehoeftes as $wb): ?>
                            <option value="<?= htmlspecialchars($wb) ?>"
                                <?= $waterbehoefte_filter === $wb ? 'selected' : '' ?>>
                                <?= htmlspecialchars($wb) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="lichtbehoefte">Lichtbehoefte</label>
                    <select name="lichtbehoefte" id="lichtbehoefte">
                        <option value="">Alle</option>
                        <?php foreach ($lichtbehoeftes as $lb): ?>
                            <option value="<?= htmlspecialchars($lb) ?>"
                                <?= $lichtbehoefte_filter === $lb ? 'selected' : '' ?>>
                                <?= htmlspecialchars($lb) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="bloeitijd">Bloeitijd</label>
                    <select name="bloeitijd" id="bloeitijd">
                        <option value="">Alle seizoenen</option>
                        <?php foreach ($bloeitijden as $bt): ?>
                            <option value="<?= htmlspecialchars($bt) ?>"
                                <?= $bloeitijd_filter === $bt ? 'selected' : '' ?>>
                                <?= htmlspecialchars($bt) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="kleur">Kleur</label>
                    <select name="kleur" id="kleur">
                        <option value="">Alle kleuren</option>
                        <?php foreach ($kleuren as $k): ?>
                            <option value="<?= htmlspecialchars($k) ?>"
                                <?= $kleur_filter === $k ? 'selected' : '' ?>>
                                <?= htmlspecialchars($k) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="sorteer">Sorteren op</label>
                    <select name="sorteer" id="sorteer">
                        <?php foreach ($sorteer_opties as $sleutel => [, , $label]): ?>
                            <option value="<?= $sleutel ?>"
                                <?= $sorteer === $sleutel ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="per_pagina">Per pagina</label>
                    <select name="per_pagina" id="per_pagina">
                        <?php foreach ($per_pagina_opties as $opt): ?>
                            <option value="<?= $opt ?>"
                                <?= $per_pagina === $opt ? 'selected' : '' ?>>
                                <?= $opt ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                
                <div class="toggle-field">
                    <label class="toggle-switch" for="verberg_uitverkocht">
                        <input type="checkbox"
                               name="verberg_uitverkocht"
                               id="verberg_uitverkocht"
                               value="1"
                               <?= $verberg_uitverkocht ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                    <label for="verberg_uitverkocht">Verberg uitverkocht</label>
                </div>

                <button type="submit">Toepassen</button>

                

            </form>

            <form> 
                <div class="field">
                <input type="text" name="zoekterm" id="zoekterm" placeholder="Zoek planten...">
                </div>
            </form>

            <!-- ── Resultateninfo ── -->
            <p class="resultaten-info">
                <?= $totaal ?> plant<?= $totaal !== 1 ? 'en' : '' ?> gevonden
                <?php
                    $actief = [];
                    if ($standplaats_filter   !== '') $actief[] = 'Standplaats: <strong>'  . htmlspecialchars($standplaats_filter)   . '</strong>';
                    if ($waterbehoefte_filter !== '') $actief[] = 'Water: <strong>'         . htmlspecialchars($waterbehoefte_filter) . '</strong>';
                    if ($lichtbehoefte_filter !== '') $actief[] = 'Licht: <strong>'         . htmlspecialchars($lichtbehoefte_filter) . '</strong>';
                    if ($bloeitijd_filter     !== '') $actief[] = 'Bloeitijd: <strong>'     . htmlspecialchars($bloeitijd_filter)     . '</strong>';
                    if ($kleur_filter         !== '') $actief[] = 'Kleur: <strong>'         . htmlspecialchars($kleur_filter)         . '</strong>';
                    if ($verberg_uitverkocht)         $actief[] = '<strong>Uitverkocht verborgen</strong>';
                    if ($actief) echo ' · ' . implode(' · ', $actief);
                ?>
            </p>

            <!-- ── Plantenoverzicht ── -->
            <div class="assortment-grid">
                <?php foreach ($plants as $plant):
                    $uitverkocht = (int)$plant['voorraad'] === 0;
                ?>
                    <div class="assortment-item<?= $uitverkocht ? ' uitverkocht' : '' ?>">
                        <img src="plant_images/<?= htmlspecialchars($plant['overview_image']) ?>"
                             loading="lazy"
                             alt="<?= htmlspecialchars($plant['naam']) ?>">
                        <div class="assortment-caption">
                            <?php if ($uitverkocht): ?>
                                <span class="uitverkocht-badge">Uitverkocht</span>
                            <?php endif; ?>
                            <span class="plant-naam"><?= htmlspecialchars($plant['naam']) ?></span>
                            <span class="plant-prijs">
                                Al vanaf €<?= number_format($plant['verkoopprijs_eur'], 2, ',', '.') ?>,-
                            </span>
                            <button class="add-to-cart-btn" <?= $uitverkocht ? 'disabled' : '' ?>>+</button>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($plants)): ?>
                    <p style="color:#666; padding:1rem 0;">
                        Geen planten gevonden met deze filters.
                    </p>
                <?php endif; ?>
            </div>

            <!-- ── Paginering ── -->
            <?php if ($totaal_paginas > 1): ?>
                <div class="paginering">
                    <?php if ($pagina > 1): ?>
                        <a href="<?= htmlspecialchars(page_url($pagina - 1)) ?>">← Vorige</a>
                    <?php endif; ?>

                    <span>Pagina <?= $pagina ?> van <?= $totaal_paginas ?></span>

                    <?php if ($pagina < $totaal_paginas): ?>
                        <a href="<?= htmlspecialchars(page_url($pagina + 1)) ?>">Volgende →</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>

    <footer>
        <div class="footer-container">

            <div class="opening">
                <h2>Openingstijden</h2>
                <div class="row">
                    <span>Ma–vr</span>
                    <span>12:00 – 17:00</span>
                </div>
                <div class="row">
                    <span>Zaterdag</span>
                    <span>10:00 – 17:00</span>
                </div>
            </div>

            <div class="contact">
                <p>email&nbsp;&nbsp;&nbsp;&nbsp;: contact@noraflora.com</p>
                <p>telefoon&nbsp;: 0612345678</p>
                <p>adres&nbsp;&nbsp;&nbsp;&nbsp;: Zwolle Pannekoekendijk420B</p>
            </div>

        </div>
    </footer>

    <script src="cart-icon-counter.js"></script>
</body>

</html>
