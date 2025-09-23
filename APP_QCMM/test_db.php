<?php
try {
    $pdo = new PDO(
        "pgsql:host=127.0.0.1;port=5432;dbname=qcm_net", 
        "postgres", 
        "azertyuiop"  // Remplace par ton vrai mot de passe
    );
    echo "Connexion PostgreSQL réussie !\n";
    
    // Test d'une requête simple
    $result = $pdo->query("SELECT version()");
    $version = $result->fetch();
    echo "Version PostgreSQL : " . $version[0] . "\n";
    
} catch (PDOException $e) {
    echo "Erreur de connexion : " . $e->getMessage() . "\n";
}
?>