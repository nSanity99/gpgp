<?php
session_start();
require_once 'db_config.php';

// --- Blocco Debug e Configurazione Sessione/Log ---
ini_set('log_errors', 1);
ini_set('error_log', 'C:/xampp/php_error.log');
error_reporting(E_ALL);

$timestamp = date("Y-m-d H:i:s");
$ruolo_admin_atteso = 'admin';

$action_for_log = isset($_GET['action']) ? $_GET['action'] : 'list';
error_log("--- [{$timestamp}] Accesso a gestioneutenze.php (View: {$action_for_log}) UTENTE: " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'NON LOGGATO') . " ---");

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true || 
    !isset($_SESSION['ruolo']) || $_SESSION['ruolo'] !== $ruolo_admin_atteso) {
    error_log("--- [{$timestamp}] Accesso NEGATO a gestioneutenze.php (non admin o non loggato) ---");
    header("Location: login.php");
    exit;
}

$username_display_gu = htmlspecialchars(isset($_SESSION['username']) ? $_SESSION['username'] : 'N/A');
$user_role_display_gu = htmlspecialchars(isset($_SESSION['ruolo']) ? $_SESSION['ruolo'] : 'N/A');

// Elenco gruppi di lavoro disponibili
$gruppi_lavoro = [
    'ABA', 'Amm. Riabilitazione', 'Amministrazione', 'Assistenti Direzione',
    'Assistenti Sociali', 'Call Center', 'Cardiologia', 'Direttore', 'Infermeria',
    'Logopediste', 'Palestra', 'Semiconvitto', 'TO', 'Ufficio Personale',
    'Ufficio Planning'
];


// --- Connessione al Database e logica per la gestione utenti ---
$conn_gu = new mysqli($db_host, $db_user, $db_pass, $db_name);

$users_list = [];
$db_error_message = null;
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$user_id_to_edit = null;
$user_to_edit = null;

if ($conn_gu->connect_error) {
    error_log("[gestioneutenze.php] Errore connessione DB: " . $conn_gu->connect_error);
    $db_error_message = "Impossibile caricare i dati: errore di connessione al database.";
} else {
    $sql_users = "SELECT id, username, email, nome, ruolo, gruppo_lavoro, data_creazione FROM utenti ORDER BY username ASC";
    $result_users = $conn_gu->query($sql_users);
    if ($result_users) {
        while ($row = $result_users->fetch_assoc()) { $users_list[] = $row; }
        $result_users->free();
    } else {
        error_log("[gestioneutenze.php] Errore query lista utenti: " . $conn_gu->error);
        $db_error_message = "Errore nel caricamento della lista utenti.";
    }

    if ($action === 'edit' && isset($_GET['id'])) {
        $user_id_to_edit = intval($_GET['id']);
        $stmt_edit = $conn_gu->prepare("SELECT id, username, email, nome, ruolo, gruppo_lavoro FROM utenti WHERE id = ?");
        if ($stmt_edit) {
            $stmt_edit->bind_param("i", $user_id_to_edit);
            $stmt_edit->execute();
            $result_edit = $stmt_edit->get_result();
            $user_to_edit = $result_edit->fetch_assoc();
            if (!$user_to_edit) {
                error_log("[gestioneutenze.php] Tentativo di modifica utente ID {$user_id_to_edit} non trovato.");
                $_SESSION['error_message_usermgmt'] = "Utente da modificare non trovato (ID: ".htmlspecialchars($user_id_to_edit).").";
                 header("Location: gestioneutenze.php?action=list_feedback"); 
                 exit;
            }
            $stmt_edit->close();
        } else {
             error_log("[gestioneutenze.php] Errore preparazione statement per modifica utente: " . $conn_gu->error);
             $db_error_message = "Errore nel caricamento dati utente per modifica.";
             $action = 'list';
        }
    }
    $conn_gu->close();
}

$success_message_usermgmt = isset($_SESSION['success_message_usermgmt']) ? $_SESSION['success_message_usermgmt'] : null;
$error_message_usermgmt = isset($_SESSION['error_message_usermgmt']) ? $_SESSION['error_message_usermgmt'] : null;
unset($_SESSION['success_message_usermgmt'], $_SESSION['error_message_usermgmt']);

if ($action === 'list_feedback' || strpos($action, 'user_') === 0) { // user_created, user_updated
    $action = 'list';
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestione Utenze - Richiesta Acquisti</title>
    <link rel="stylesheet" href="style.css"> 
    <style>
        body {
            background-color: #f8f9fa; /* Stesso sfondo della dashboard */
            color: #495057;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
        }
        .page-outer-container { /* Simile a .dashboard-container */
            max-width: 1100px; 
            padding: 0;
            animation: fadeInSlideUp 0.6s ease-out forwards; 
            margin: 25px auto; 
        }

        /* --- Header Design (simile a dashboard.php) --- */
        .module-header { /* Simile a .dashboard-header */
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #ffffff;
            padding: 18px 30px; /* Leggermente meno padding verticale */
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            border-bottom: 4px solid #B08D57;
            margin-bottom: 30px;
        }
        .header-branding { display: flex; align-items: center; }
        .header-branding .logo { max-height: 45px; margin-right: 15px; display: block; }
        .header-titles h1 { font-size: 1.5em; color: #2E572E; margin: 0; font-weight: 600; line-height: 1.2; }
        .header-titles h2 { font-size: 0.9em; color: #6c757d; margin: 0; font-weight: 400; }

        .user-session-controls { text-align: right; font-size: 0.9em; color: #555; display: flex; align-items: center; gap: 15px; }
        .user-session-controls .user-info span { display: block; line-height: 1.3; }
        .user-session-controls strong { font-weight: 600; color: #333; }
        .user-session-controls .nav-link-button,
        .user-session-controls .logout-button { 
            padding: 7px 14px; font-size: 0.9em; color: white !important; text-decoration: none; 
            border-radius: 5px; transition: background-color 0.2s ease, transform 0.2s ease; font-weight: 500;
        }
        .user-session-controls .nav-link-button { background-color: #6c757d; /* Grigio per "Torna a Dashboard" */ }
        .user-session-controls .nav-link-button:hover { background-color: #5a6268; transform: translateY(-1px); }
        .user-session-controls .logout-button { background-color: #D42A2A; }
        .user-session-controls .logout-button:hover { background-color: #c82333; transform: translateY(-1px); }
        /* --- Fine Header Design --- */

        .module-content { /* Simile a .main-content-area ma specifico per il modulo */
            background-color: #ffffff;
            padding: 25px 30px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        .app-section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #e9ecef; }
        .app-section-header h2 { font-size: 1.4em; /* 22px */ color: #2E572E; margin: 0; font-weight: 600;}
        .app-section-header .action-buttons a, .app-section-header .action-buttons button { margin-left: 10px; }
        
        /* Stili per pulsanti, tabelle, form (mantenuti e leggermente adattati) */
        .admin-button { background-color: #B08D57; color: white; padding: 9px 18px; border: none; border-radius: 5px; cursor: pointer; font-size: 0.95em; text-decoration: none; display: inline-block; transition: background-color 0.2s ease, transform 0.2s ease; font-weight: 500;}
        .admin-button:hover { background-color: #9c7b4c; transform: translateY(-1px); box-shadow: 0 2px 4px rgba(0,0,0,0.1);}
        .admin-button.secondary { background-color: #6c757d; }
        .admin-button.secondary:hover { background-color: #5a6268; }
        
        table.users-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 0.95em; }
        .users-table th, .users-table td { border: 1px solid #dee2e6; padding: 12px 15px; text-align: left; vertical-align: middle;}
        .users-table th { background-color: #f8f9fa; color: #495057; font-weight: 600; /* Semi-bold */}
        .users-table tr:nth-child(even) { background-color: #fcfdff; /* Bianco alternato molto leggero */}
        .users-table tr:hover { background-color: #eef4f8; /* Azzurrino leggero al hover */}
        .users-table .actions-cell a, .users-table .actions-cell button { margin-right: 6px; padding: 6px 10px; font-size: 0.9em; }

        .form-container { margin-top: 25px; padding: 25px; background-color: #f8f9fa; border: 1px solid #e9ecef; border-radius: 6px; }
        .form-container h3 { margin-top: 0; margin-bottom: 20px; color: #2E572E; font-size: 1.3em; font-weight: 600;}
        /* Assicurati che .form-group, input, select, label da style.css siano coerenti */
        /* Esempio di override se necessario per input in questo contesto */
        .form-container .form-control { /* Se usi questa classe per gli input */
            border-radius: 5px;
            border: 1px solid #ced4da;
            padding: 10px 12px;
        }
        .form-container .form-control:focus {
            border-color: #B08D57;
            box-shadow: 0 0 0 0.2rem rgba(176, 141, 87, 0.25);
        }


        .success-message { color: #0f5132; background-color: #d1e7dd; border: 1px solid #badbcc; padding: 12px 15px; border-radius: 5px; margin-bottom: 20px; }
        .error-message-usermgmt { color: #842029; background-color: #f8d7da; border: 1px solid #f5c2c7; padding: 12px 15px; border-radius: 5px; margin-bottom: 20px; }
        .error-message { /* Messaggio di errore DB generico */
            color: #842029; background-color: #f8d7da; border: 1px solid #f5c2c7; padding: 12px 15px; border-radius: 5px; margin-bottom: 20px;
        }


        .footer-logo-area { text-align: center; margin-top: 40px; padding-top: 25px; border-top: 1px solid #e9ecef; }
        .footer-logo-area img { max-width: 60px; opacity: 0.5; }

    </style>
</head>
<body>
    <div class="page-outer-container">
        <header class="module-header">
            <div class="header-branding">
                <a href="dashboard.php" class="header-logo-link"><img src="logo.png" alt="Logo Gruppo Vitolo" class="logo"></a>
                <div class="header-titles">
                    <h1>Gestione Utenze</h1>
                    <h2>Applicazione Richiesta Acquisti</h2>
                </div>
            </div>
            <div class="user-session-controls">
                <div class="user-info">
                    <span><strong><?php echo $username_display_gu; ?></strong></span>
                    <span>(<?php echo $user_role_display_gu; ?>)</span>
                </div>
                <a href="dashboard.php" class="nav-link-button">Dashboard</a>
                <a href="logout.php" class="logout-button">Logout</a>
            </div>
        </header>
        
        <main class="module-content" id="gestione-utenze-content">
            <div class="app-section-header">
                <h2>Elenco Utenti Registrati</h2>
                <?php if ($action !== 'create_user' && $action !== 'edit'): ?>
                <div class="action-buttons">
                    <a href="gestioneutenze.php?action=create_user#user-form-anchor" class="admin-button">Crea Nuovo Utente</a>
                </div>
                <?php endif; ?>
            </div>

            <?php if (isset($db_error_message)): ?>
                <p class="error-message"><?php echo htmlspecialchars($db_error_message); ?></p>
            <?php endif; ?>
            <?php if ($success_message_usermgmt): ?>
                <p class="success-message"><?php echo htmlspecialchars($success_message_usermgmt); ?></p>
            <?php endif; ?>
            <?php if ($error_message_usermgmt): ?>
                <p class="error-message-usermgmt"><?php echo htmlspecialchars($error_message_usermgmt); ?></p>
            <?php endif; ?>

            <?php if ($action === 'list'): ?>
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th><th>Username</th><th>Email</th><th>Nome</th><th>Ruolo</th><th>Gruppo</th><th>Data Creazione</th><th>Azioni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users_list)): ?>
                            <?php foreach ($users_list as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars(isset($user['email']) ? $user['email'] : '-'); ?></td>
                                    <td><?php echo htmlspecialchars(isset($user['nome']) ? $user['nome'] : '-'); ?></td>
                                    <td><?php echo htmlspecialchars($user['ruolo']); ?></td>
                                    <td><?php echo htmlspecialchars($user['gruppo_lavoro'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($user['data_creazione']))); ?></td>
                                    <td class="actions-cell">
                                        <a href="gestioneutenze.php?action=edit&id=<?php echo $user['id']; ?>#user-form-anchor" class="admin-button secondary">Modifica</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align:center; padding: 20px;">Nessun utente trovato nel sistema.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div id="user-form-anchor"></div>

            <?php if ($action === 'create_user' || ($action === 'edit' && $user_to_edit)): ?>
                <div class="form-container">
                    <h3><?php echo $action === 'create_user' ? 'Crea Nuovo Utente' : 'Modifica Utente: ' . htmlspecialchars($user_to_edit['username']); ?></h3>
                    <form action="dashboard_actions.php" method="POST">
                        <?php if ($action === 'edit'): ?>
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($user_to_edit['id']); ?>">
                        <?php endif; ?>
                        <input type="hidden" name="form_action" value="<?php echo $action === 'create_user' ? 'create_user_submit' : 'edit_user_submit'; ?>">
                        <input type="hidden" name="redirect_success" value="gestioneutenze.php?action=list_feedback">
                        <input type="hidden" name="redirect_error" value="gestioneutenze.php?action=<?php echo $action; echo ($action==='edit' && isset($user_to_edit['id']) ? '&id='.$user_to_edit['id'] : ''); ?>">

                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input type="text" id="username" name="username" class="form-control" value="<?php echo htmlspecialchars(isset($user_to_edit['username']) ? $user_to_edit['username'] : ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars(isset($user_to_edit['email']) ? $user_to_edit['email'] : ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="nome">Nome Completo:</label>
                            <input type="text" id="nome" name="nome" class="form-control" value="<?php echo htmlspecialchars(isset($user_to_edit['nome']) ? $user_to_edit['nome'] : ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="password">Password: <?php echo $action === 'edit' ? '(lascia vuoto per non cambiare)' : ''; ?></label>
                            <input type="password" id="password" name="password" class="form-control" <?php echo $action === 'create_user' ? 'required' : ''; ?>>
                        </div>
                        <div class="form-group">
                            <label for="ruolo">Ruolo:</label>
                            <select id="ruolo" name="ruolo" class="form-control" required>
                                <option value="user" <?php echo ((isset($user_to_edit['ruolo']) && $user_to_edit['ruolo'] === 'user') || (!isset($user_to_edit) && $action === 'create_user')) ? 'selected' : ''; ?>>User</option>
                                <option value="admin" <?php echo (isset($user_to_edit['ruolo']) && $user_to_edit['ruolo'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="gruppo_lavoro">Gruppo di Lavoro:</label>
                            <select id="gruppo_lavoro" name="gruppo_lavoro" class="form-control" required>
                                <?php foreach ($gruppi_lavoro as $gruppo): ?>
                                    <option value="<?php echo $gruppo; ?>" <?php echo (isset($user_to_edit['gruppo_lavoro']) && $user_to_edit['gruppo_lavoro'] === $gruppo) ? 'selected' : ''; ?>><?php echo $gruppo; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="admin-button"><?php echo $action === 'create_user' ? 'Crea Utente' : 'Salva Modifiche'; ?></button>
                        <a href="gestioneutenze.php?action=list" class="admin-button secondary" style="margin-left: 10px;">Annulla</a>
                    </form>
                </div>
            <?php elseif ($action === 'edit' && !$user_to_edit && isset($_GET['id'])): ?>
                <p class="error-message-usermgmt">Impossibile caricare i dati dell'utente (ID: <?php echo htmlspecialchars($_GET['id']); ?>) per la modifica. L'utente potrebbe non esistere.</p>
                <a href="gestioneutenze.php?action=list" class="admin-button secondary">Torna alla Lista Utenti</a>
            <?php endif; ?>
        </main>
        <footer class="footer-logo-area">
            <img src="logo.png" alt="Logo Gruppo Vitolo">
        </footer>
    </div>
</body>
</html>