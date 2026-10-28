<?php
/* Executado pelo install.sh. Não sobrescreve uma tabela existente. */
require_once dirname(__FILE__) . '/config.php';

try {
    $db = csat_connection();
    $sql = "CREATE TABLE IF NOT EXISTS csat_avaliacoes (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        telefone VARCHAR(32) NOT NULL,
        ramal VARCHAR(32) NOT NULL,
        atendente VARCHAR(120) NOT NULL,
        fila VARCHAR(120) NOT NULL,
        nota TINYINT UNSIGNED NOT NULL,
        data_avaliacao DATETIME NOT NULL,
        PRIMARY KEY (id),
        KEY idx_csat_data (data_avaliacao),
        KEY idx_csat_ramal (ramal),
        KEY idx_csat_fila (fila)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8";

    if (!$db->query($sql)) {
        throw new Exception('Não foi possível criar ou verificar a tabela csat_avaliacoes.');
    }

    $db->close();
    echo "Tabela csat_avaliacoes pronta.\n";
    exit(0);
} catch (Exception $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
?>
