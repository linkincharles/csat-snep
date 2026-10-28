<?php
require_once dirname(__FILE__) . '/config.php';

function csat_date($value) {
    if ($value === '') {
        return '';
    }
    $date = DateTime::createFromFormat('Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value) ? $value : '';
}

function csat_bind($statement, $types, $values) {
    if (count($values) === 0) {
        return;
    }
    $params = array($types);
    foreach ($values as $key => $value) {
        $params[] = &$values[$key];
    }
    call_user_func_array(array($statement, 'bind_param'), $params);
}

function csat_query_url($values) {
    $clean = array();
    foreach ($values as $key => $value) {
        if ($value !== '' && $value !== null && $value !== false) {
            $clean[$key] = $value;
        }
    }
    return 'index.php' . (count($clean) ? '?' . http_build_query($clean) : '');
}

function csat_scalar($db, $sql, $types, $values) {
    $statement = $db->prepare($sql);
    if (!$statement) {
        throw new Exception('Não foi possível preparar a consulta de indicadores.');
    }
    csat_bind($statement, $types, $values);
    $statement->execute();
    $statement->bind_result($total, $media, $nota5, $nota1);
    $statement->fetch();
    $statement->close();
    return array('total' => $total, 'media' => $media, 'nota_5' => $nota5, 'nota_1' => $nota1);
}

$period = isset($_GET['periodo']) ? $_GET['periodo'] : '';
if (!in_array($period, array('dia', 'semana', 'mes'))) {
    $period = '';
}
$dateStart = isset($_GET['data_inicio']) ? csat_date($_GET['data_inicio']) : '';
$dateEnd = isset($_GET['data_fim']) ? csat_date($_GET['data_fim']) : '';
if ($period === 'dia') {
    $dateStart = date('Y-m-d');
    $dateEnd = $dateStart;
} elseif ($period === 'semana') {
    $dateStart = date('Y-m-d', strtotime('-' . (date('N') - 1) . ' days'));
    $dateEnd = date('Y-m-d', strtotime('+' . (7 - date('N')) . ' days'));
} elseif ($period === 'mes') {
    $dateStart = date('Y-m-01');
    $dateEnd = date('Y-m-t');
}
$queue = isset($_GET['fila']) ? trim($_GET['fila']) : '';
$extension = isset($_GET['ramal']) ? trim($_GET['ramal']) : '';
$rowsPerPage = isset($_GET['linhas']) ? (int) $_GET['linhas'] : 50;
if (!in_array($rowsPerPage, array(50, 100, 200))) {
    $rowsPerPage = 50;
}
$currentPage = isset($_GET['pagina']) ? (int) $_GET['pagina'] : 1;
if ($currentPage < 1) {
    $currentPage = 1;
}
$error = '';
$ratings = array();
$queues = array();
$extensions = array();
$summary = array('total' => 0, 'media' => null, 'nota_5' => 0, 'nota_1' => 0);

try {
    $db = csat_connection();
    $where = array();
    $types = '';
    $values = array();

    if ($dateStart !== '') {
        $where[] = 'data_avaliacao >= ?';
        $types .= 's';
        $values[] = $dateStart . ' 00:00:00';
    }
    if ($dateEnd !== '') {
        $where[] = 'data_avaliacao <= ?';
        $types .= 's';
        $values[] = $dateEnd . ' 23:59:59';
    }
    if ($queue !== '') {
        $where[] = 'fila = ?';
        $types .= 's';
        $values[] = $queue;
    }
    if ($extension !== '') {
        $where[] = 'ramal = ?';
        $types .= 's';
        $values[] = $extension;
    }

    $filterSql = count($where) ? ' WHERE ' . implode(' AND ', $where) : '';
    $summary = csat_scalar(
        $db,
        'SELECT COUNT(*) AS total, AVG(nota) AS media, SUM(nota = 5) AS nota_5, SUM(nota = 1) AS nota_1 FROM csat_avaliacoes' . $filterSql,
        $types,
        $values
    );
    $totalRows = (int) $summary['total'];
    $totalPages = max(1, (int) ceil($totalRows / $rowsPerPage));
    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
    }
    $offset = ($currentPage - 1) * $rowsPerPage;

    $result = $db->query("SELECT DISTINCT fila FROM csat_avaliacoes WHERE fila IS NOT NULL AND fila <> '' ORDER BY fila");
    while ($row = $result->fetch_assoc()) {
        $queues[] = $row['fila'];
    }
    $result = $db->query("SELECT DISTINCT ramal FROM csat_avaliacoes WHERE ramal IS NOT NULL AND ramal <> '' ORDER BY ramal");
    while ($row = $result->fetch_assoc()) {
        $extensions[] = $row['ramal'];
    }

    $statement = $db->prepare('SELECT data_avaliacao, telefone, ramal, atendente, fila, nota FROM csat_avaliacoes' . $filterSql . ' ORDER BY data_avaliacao DESC LIMIT ' . $offset . ', ' . $rowsPerPage);
    if (!$statement) {
        throw new Exception('Não foi possível preparar a consulta das avaliações.');
    }
    csat_bind($statement, $types, $values);
    $statement->execute();
    $statement->bind_result($dataAvaliacao, $telefone, $ramal, $atendente, $fila, $nota);
    while ($statement->fetch()) {
        $ratings[] = array(
            'data_avaliacao' => $dataAvaliacao,
            'telefone' => $telefone,
            'ramal' => $ramal,
            'atendente' => $atendente,
            'fila' => $fila,
            'nota' => $nota
        );
    }
    $statement->close();
    $db->close();
} catch (Exception $exception) {
    $error = $exception->getMessage();
}

$average = ($summary['media'] !== null) ? number_format((float) $summary['media'], 2, ',', '.') : '—';
$totalRows = isset($totalRows) ? $totalRows : 0;
$totalPages = isset($totalPages) ? $totalPages : 1;
$firstRow = $totalRows ? (($currentPage - 1) * $rowsPerPage) + 1 : 0;
$lastRow = min($currentPage * $rowsPerPage, $totalRows);
$queryBase = array('periodo' => $period, 'data_inicio' => $period === '' ? $dateStart : '', 'data_fim' => $period === '' ? $dateEnd : '', 'fila' => $queue, 'ramal' => $extension, 'linhas' => $rowsPerPage);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>CSAT | Avaliação de atendimento</title>
    <script>(function(){try{if(localStorage.getItem('csat-theme') === 'dark'){document.documentElement.className = 'dark-theme';}}catch(e){}}());</script>
    <link rel="stylesheet" href="assets/style-v2.css?v=3">
</head>
<body>
    <main class="container">
        <header class="page-header">
            <div class="header-copy">
                <p class="eyebrow"><span class="status-dot"></span>PRATICAR</p>
                <h1>Avaliação de atendimento</h1>
                <p class="subtitle">Acompanhe as avaliações registradas após as chamadas.</p>
            </div>
            <div class="header-actions"><div class="header-note"><span>Visão consolidada</span><strong>Notas de 1 a 5</strong></div><button class="theme-toggle" id="theme-toggle" type="button" aria-pressed="false">Modo escuro</button></div>
        </header>

        <?php if ($error !== ''): ?>
            <div class="message error"><?php echo csat_html($error); ?></div>
        <?php else: ?>
            <section class="cards" aria-label="Indicadores">
                <article class="card average-card"><span class="metric-label">Média geral</span><strong><?php echo $average; ?></strong><small>de 5 pontos</small></article>
                <article class="card total-card"><span class="metric-label">Total de avaliações</span><strong><?php echo (int) $summary['total']; ?></strong><small>no período selecionado</small></article>
                <article class="card positive-card"><span class="metric-label">Nota 5</span><strong><?php echo (int) $summary['nota_5']; ?></strong><small>avaliações máximas</small></article>
                <article class="card alert-card"><span class="metric-label">Nota 1</span><strong><?php echo (int) $summary['nota_1']; ?></strong><small>avaliações mínimas</small></article>
            </section>

            <section class="panel filters-panel">
                <div class="section-title"><div><p class="section-kicker">Consulta</p><h2>Filtros</h2></div><p>Refine os indicadores e a lista abaixo.</p></div>
                <form method="get" action="">
                    <div class="period-shortcuts" aria-label="Atalhos de período">
                        <span>Período rápido</span>
                        <?php foreach (array('dia' => 'Hoje', 'semana' => 'Esta semana', 'mes' => 'Este mês') as $key => $label): ?>
                            <?php $periodQuery = array('periodo' => $key, 'fila' => $queue, 'ramal' => $extension, 'linhas' => $rowsPerPage); ?>
                            <a class="period-link<?php echo $period === $key ? ' active' : ''; ?>" href="<?php echo csat_html(csat_query_url($periodQuery)); ?>"><?php echo $label; ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="filters">
                        <label>Data inicial<input type="date" name="data_inicio" value="<?php echo csat_html($dateStart); ?>"></label>
                        <label>Data final<input type="date" name="data_fim" value="<?php echo csat_html($dateEnd); ?>"></label>
                        <label>Fila<select name="fila"><option value="">Todas as filas</option><?php foreach ($queues as $item): ?><option value="<?php echo csat_html($item); ?>"<?php echo $queue === $item ? ' selected' : ''; ?>><?php echo csat_html($item); ?></option><?php endforeach; ?></select></label>
                        <label>Ramal<select name="ramal"><option value="">Todos os ramais</option><?php foreach ($extensions as $item): ?><option value="<?php echo csat_html($item); ?>"<?php echo $extension === $item ? ' selected' : ''; ?>><?php echo csat_html($item); ?></option><?php endforeach; ?></select></label>
                        <label>Linhas por página<select name="linhas"><option value="50"<?php echo $rowsPerPage === 50 ? ' selected' : ''; ?>>50 linhas</option><option value="100"<?php echo $rowsPerPage === 100 ? ' selected' : ''; ?>>100 linhas</option><option value="200"<?php echo $rowsPerPage === 200 ? ' selected' : ''; ?>>200 linhas</option></select></label>
                    </div>
                    <input type="hidden" name="periodo" value="">
                    <div class="actions"><button type="submit">Aplicar filtros</button><a href="index.php">Limpar filtros</a></div>
                </form>
            </section>

            <section class="panel table-panel">
                <div class="table-heading"><div><p class="section-kicker">Histórico</p><h2>Avaliações</h2></div><span>Exibindo <?php echo $firstRow; ?> a <?php echo $lastRow; ?> de <?php echo $totalRows; ?> registros</span></div>
                <?php if (count($ratings) === 0): ?>
                    <div class="empty">Nenhuma avaliação encontrada para os filtros selecionados.</div>
                <?php else: ?>
                    <div class="table-wrap"><table>
                        <thead><tr><th>Data</th><th>Telefone</th><th>Ramal</th><th>Atendente</th><th>Fila</th><th>Nota</th></tr></thead>
                        <tbody><?php foreach ($ratings as $rating): ?><tr>
                            <td data-label="Data"><?php echo csat_html(date('d/m/Y H:i', strtotime($rating['data_avaliacao']))); ?></td>
                            <td data-label="Telefone"><?php echo csat_html($rating['telefone']); ?></td>
                            <td data-label="Ramal"><?php echo csat_html($rating['ramal']); ?></td>
                            <td data-label="Atendente"><?php echo csat_html($rating['atendente']); ?></td>
                            <td data-label="Fila"><?php echo csat_html($rating['fila']); ?></td>
                            <td data-label="Nota"><span class="score score-<?php echo (int) $rating['nota']; ?>"><?php echo (int) $rating['nota']; ?></span></td>
                        </tr><?php endforeach; ?></tbody>
                    </table></div>
                    <?php if ($totalPages > 1): ?>
                        <nav class="pagination" aria-label="Paginação de avaliações">
                            <?php $previousQuery = $queryBase; $previousQuery['pagina'] = $currentPage - 1; ?>
                            <?php if ($currentPage > 1): ?><a href="<?php echo csat_html(csat_query_url($previousQuery)); ?>">‹ Anterior</a><?php else: ?><span class="disabled">‹ Anterior</span><?php endif; ?>
                            <span class="page-status">Página <?php echo $currentPage; ?> de <?php echo $totalPages; ?></span>
                            <?php $nextQuery = $queryBase; $nextQuery['pagina'] = $currentPage + 1; ?>
                            <?php if ($currentPage < $totalPages): ?><a href="<?php echo csat_html(csat_query_url($nextQuery)); ?>">Próxima ›</a><?php else: ?><span class="disabled">Próxima ›</span><?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
    <script>
    (function () {
        var button = document.getElementById('theme-toggle');
        function updateThemeButton() {
            var dark = document.documentElement.className.indexOf('dark-theme') !== -1 || document.body.className.indexOf('dark-theme') !== -1;
            document.body.className = dark ? 'dark-theme' : '';
            button.innerHTML = dark ? 'Modo claro' : 'Modo escuro';
            button.setAttribute('aria-pressed', dark ? 'true' : 'false');
        }
        updateThemeButton();
        button.onclick = function () {
            var dark = document.documentElement.className.indexOf('dark-theme') !== -1 || document.body.className.indexOf('dark-theme') !== -1;
            document.documentElement.className = dark ? '' : 'dark-theme';
            document.body.className = dark ? '' : 'dark-theme';
            try { localStorage.setItem('csat-theme', dark ? 'light' : 'dark'); } catch (e) {}
            updateThemeButton();
        };
    }());
    </script>
</body>
</html>
