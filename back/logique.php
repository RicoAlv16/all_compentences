<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Gérer les requêtes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Configuration de la base de données
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'allcompetences';

try {
    // Connexion à la base de données
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erreur de connexion à la base de données: ' . $e->getMessage()
    ]);
    exit();
}

// Traitement des requêtes POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Récupération des données JSON
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Vérification que les données ont été reçues
    if (!$data) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Aucune donnée reçue ou format JSON invalide'
        ]);
        exit();
    }
    
    // Validation des champs obligatoires
    $required_fields = ['nom', 'prenom', 'email', 'type_personne', 'sujet', 'message'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Champs obligatoires manquants: ' . implode(', ', $missing_fields)
        ]);
        exit();
    }
    
    // Validation de l'email
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Format d\'email invalide'
        ]);
        exit();
    }
    
    // Validation du type de personne
    if (!in_array($data['type_personne'], ['freelance', 'entreprise'])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Type de personne invalide'
        ]);
        exit();
    }
    
    // Validation du téléphone (optionnel mais si présent, doit être valide)
    if (!empty($data['telephone'])) {
        $phone_pattern = '/^[0-9\s\-\+\(\)]{10,}$/';
        if (!preg_match($phone_pattern, $data['telephone'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Format de téléphone invalide'
            ]);
            exit();
        }
    }
    
    try {
        // Préparation de la requête d'insertion
        $sql = "INSERT INTO contact (
                    nom, 
                    prenom, 
                    adresse, 
                    mail, 
                    telephone, 
                    type, 
                    sujet, 
                    message
                ) VALUES (
                    :nom, 
                    :prenom, 
                    :adresse, 
                    :mail, 
                    :telephone, 
                    :type, 
                    :sujet, 
                    :message
                )";
        
        $stmt = $pdo->prepare($sql);
        
        // Exécution de la requête avec les données
        $result = $stmt->execute([
            ':nom' => trim($data['nom']),
            ':prenom' => trim($data['prenom']),
            ':adresse' => isset($data['adresse']) ? trim($data['adresse']) : null,
            ':mail' => trim(strtolower($data['email'])), // Attention: mail dans la DB, email dans le form
            ':telephone' => isset($data['telephone']) ? trim($data['telephone']) : null,
            ':type' => $data['type_personne'],
            ':sujet' => trim($data['sujet']),
            ':message' => trim($data['message'])
        ]);
        
        if ($result) {
            $contact_id = $pdo->lastInsertId();
            
            // Optionnel: Envoyer un email de notification
            // $this->sendNotificationEmail($data);
            
            http_response_code(201);
            echo json_encode([
                'success' => true,
                'message' => 'Votre message a été envoyé avec succès !',
                'contact_id' => $contact_id
            ]);
        } else {
            throw new Exception('Erreur lors de l\'insertion des données');
        }
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erreur lors de l\'enregistrement: ' . $e->getMessage()
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
} else {
    // Méthode non autorisée
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Méthode non autorisée. Utilisez POST.'
    ]);
}

// Fonction optionnelle pour envoyer un email de notification
function sendNotificationEmail($data) {
    $to = 'infos@allcompetences.com';
    $subject = 'Nouveau contact depuis le site web - ' . $data['sujet'];
    
    $message = "Nouveau message de contact reçu:\n\n";
    $message .= "Nom: " . $data['nom'] . " " . $data['prenom'] . "\n";
    $message .= "Email: " . $data['email'] . "\n";
    $message .= "Téléphone: " . ($data['telephone'] ?? 'Non renseigné') . "\n";
    $message .= "Adresse: " . ($data['adresse'] ?? 'Non renseignée') . "\n";
    $message .= "Type: " . ($data['type_personne'] === 'freelance' ? 'Freelance' : 'Entreprise') . "\n";
    $message .= "Sujet: " . $data['sujet'] . "\n\n";
    $message .= "Message:\n" . $data['message'] . "\n";
    
    $headers = "From: noreply@allcompetences.com\r\n";
    $headers .= "Reply-To: " . $data['email'] . "\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    
    mail($to, $subject, $message, $headers);
}
?>