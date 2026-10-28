<?php
/*
 * CSAT SNEP: le as credenciais existentes em /etc/odbc.ini.
 * Nenhuma senha deve ser incluída neste arquivo.
 */

function csat_html($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function csat_odbc_value($section, $names, $default) {
    foreach ($names as $name) {
        if (isset($section[$name]) && $section[$name] !== '') {
            return $section[$name];
        }
    }
    return $default;
}

function csat_connection() {
    $odbcFile = '/etc/odbc.ini';

    if (!is_readable($odbcFile)) {
        throw new Exception('Não foi possível ler /etc/odbc.ini. Verifique a permissão do Apache.');
    }

    $ini = parse_ini_file($odbcFile, true);
    if ($ini === false || !is_array($ini)) {
        throw new Exception('O arquivo /etc/odbc.ini não pôde ser interpretado.');
    }

    /* Usa a primeira seção que tenha servidor e banco configurados. */
    $selected = null;
    foreach ($ini as $section) {
        if (!is_array($section)) {
            continue;
        }
        $host = csat_odbc_value($section, array('Server', 'SERVER', 'Host', 'HOST'), '');
        $database = csat_odbc_value($section, array('Database', 'DATABASE'), '');
        if ($host !== '' && $database !== '') {
            $selected = $section;
            break;
        }
    }

    if ($selected === null) {
        throw new Exception('Nenhuma seção com Server e Database foi encontrada em /etc/odbc.ini.');
    }

    $host = csat_odbc_value($selected, array('Server', 'SERVER', 'Host', 'HOST'), 'localhost');
    $user = csat_odbc_value($selected, array('User', 'USER', 'UID', 'uid'), 'root');
    $password = csat_odbc_value($selected, array('Password', 'PASSWORD', 'PWD', 'pwd'), '');
    $database = csat_odbc_value($selected, array('Database', 'DATABASE'), 'snep');
    $port = (int) csat_odbc_value($selected, array('Port', 'PORT'), 3306);

    $mysqli = @new mysqli($host, $user, $password, $database, $port);
    if ($mysqli->connect_errno) {
        throw new Exception('Não foi possível conectar ao banco de dados. Verifique a seção do SNEP em /etc/odbc.ini.');
    }

    $mysqli->set_charset('utf8');
    return $mysqli;
}
?>
