<?php
session_start();

// Dettagli connessione DB (gli stessi usati negli altri file)
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'gruppo_vitolo_db';

// Log per il debug
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php_error.log'); // Assicurati che sia lo stesso percorso che controlli
error_reporting(E_ALL);
$timestamp = date("Y-m-d H:i:s");

// Verifica che l'utente sia loggato
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || !isset($_SESSION['user_id'])) {
    error_log("--- [{$timestamp}] [submit_order_action.php] Tentativo di invio ordine da utente non loggato o sessione invalida. ---");
    header("Location: login.php?error=session_expired");
    exit;
}

// Verifica che la richiesta sia POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    error_log("--- [{$timestamp}] [submit_order_action.php] Accesso non POST al file. ---");
    header("Location: form_page.php?status=order_error&message=" . urlencode("Metodo di richiesta non valido."));
    exit;
}

error_log("--- [{$timestamp}] [submit_order_action.php] Ricevuta richiesta POST per invio ordine. Utente ID: {$_SESSION['user_id']} ---");

// Recupero e validazione dati principali del form
$id_utente_richiedente = filter_var($_POST['id_utente_richiedente'] ?? $_SESSION['user_id'], FILTER_VALIDATE_INT);
$nome_richiedente = trim(htmlspecialchars($_POST['nome_richiedente'] ?? $_SESSION['user_fullname'] ?? $_SESSION['username']));
$centro_costo = trim(htmlspecialchars($_POST['centro_costo'] ?? ''));
$prodotti_json = $_POST['prodotti_json'] ?? '[]'; // Stringa JSON dei prodotti
$prodotti = json_decode($prodotti_json, true); // Decodifica in array PHP

// Validazione base
if (empty($id_utente_richiedente) || empty($nome_richiedente) || empty($centro_costo)) {
    error_log("[submit_order_action.php] Dati mancanti: id_utente, nome_richiedente o centro_costo.");
    header("Location: form_page.php?status=order_error&message=" . urlencode("Dati principali mancanti."));
    exit;
}

if (empty($prodotti) || !is_array($prodotti)) {
    error_log("[submit_order_action.php] Nessun prodotto inviato o formato JSON prodotti errato.");
    header("Location: form_page.php?status=order_error&message=" . urlencode("Nessun prodotto specificato nella richiesta."));
    exit;
}

// Data richiesta: usiamo il timestamp del server al momento dell'elaborazione
$data_richiesta_sql = date('Y-m-d H:i:s');


// Connessione al database
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    error_log("[submit_order_action.php] Errore connessione DB: " . $conn->connect_error);
    header("Location: form_page.php?status=order_error&message=" . urlencode("Errore di connessione al database."));
    exit;
}

// Inizio Transazione
$conn->begin_transaction();

try {
    // 1. Inserisci l'ordine nella tabella 'ordini'
    $stmt_ordine = $conn->prepare("INSERT INTO ordini (data_richiesta, id_utente_richiedente, nome_richiedente, centro_costo, stato_ordine) VALUES (?, ?, ?, ?, ?)");
    if (!$stmt_ordine) {
        throw new Exception("Errore preparazione statement ordine: " . $conn->error);
    }
    
    $stato_ordine_iniziale = 'Inviato';
    $stmt_ordine->bind_param("sisss", $data_richiesta_sql, $id_utente_richiedente, $nome_richiedente, $centro_costo, $stato_ordine_iniziale);
    
    if (!$stmt_ordine->execute()) {
        throw new Exception("Errore esecuzione statement ordine: " . $stmt_ordine->error);
    }
    
    $id_ordine_inserito = $conn->insert_id; // Recupera l'ID dell'ordine appena inserito
    $stmt_ordine->close();

    if (!$id_ordine_inserito) {
        throw new Exception("Impossibile recuperare l'ID dell'ordine inserito.");
    }
    error_log("[submit_order_action.php] Ordine #{$id_ordine_inserito} inserito con successo.");

    // 2. Inserisci i dettagli dell'ordine (prodotti) nella tabella 'dettagli_ordine'
    $stmt_dettaglio = $conn->prepare("INSERT INTO dettagli_ordine (id_ordine, nome_prodotto, quantita, unita_misura, note_prodotto, stato_prodotto) VALUES (?, ?, ?, ?, ?, ?)");
    if (!$stmt_dettaglio) {
        throw new Exception("Errore preparazione statement dettaglio ordine: " . $conn->error);
    }
    
    $stato_prodotto_iniziale = 'Inviato';
    foreach ($prodotti as $prodotto) {
        $nome_p = trim(htmlspecialchars($prodotto['name'] ?? ''));
        $qta_p = filter_var($prodotto['quantity'] ?? 0, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1, "max_range" => 999]]);
        $um_p = trim(htmlspecialchars($prodotto['unit'] ?? ''));
        $note_p = trim(htmlspecialchars($prodotto['notes'] ?? ''));

        if (empty($nome_p) || $qta_p === false || empty($um_p)) {
            throw new Exception("Dati prodotto non validi: " . print_r($prodotto, true));
        }

        $stmt_dettaglio->bind_param("isssss", $id_ordine_inserito, $nome_p, $qta_p, $um_p, $note_p, $stato_prodotto_iniziale);
        if (!$stmt_dettaglio->execute()) {
            throw new Exception("Errore esecuzione statement dettaglio prodotto: " . $stmt_dettaglio->error . " per prodotto: " . $nome_p);
        }
        error_log("[submit_order_action.php] Dettaglio prodotto '{$nome_p}' per ordine #{$id_ordine_inserito} inserito.");
    }
    $stmt_dettaglio->close();

    // Se tutto è andato a buon fine, committa la transazione
    $conn->commit();
    error_log("[submit_order_action.php] Transazione completata con successo per ordine #{$id_ordine_inserito}.");
    header("Location: form_page.php?status=order_success");
    exit;

} catch (Exception $e) {
    // Qualcosa è andato storto, annulla la transazione
    $conn->rollback();
    error_log("[submit_order_action.php] ERRORE TRANSAZIONE: " . $e->getMessage());
    // Reindirizza con un messaggio di errore più specifico se possibile, o uno generico
    $error_message_url = urlencode("Si è verificato un errore durante il salvataggio dell'ordine: " . $e->getMessage());
    header("Location: form_page.php?status=order_error&message=" . $error_message_url);
    exit;
} finally {
    if (isset($stmt_ordine) && $stmt_ordine) $stmt_ordine->close();
    if (isset($stmt_dettaglio) && $stmt_dettaglio) $stmt_dettaglio->close();
    if ($conn) $conn->close();
}

?>