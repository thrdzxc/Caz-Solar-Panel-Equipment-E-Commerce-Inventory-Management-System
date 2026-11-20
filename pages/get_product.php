<?php
// Set header to JSON so JavaScript understands the response immediately
header('Content-Type: application/json');

// 1. Include your database connection
// Make sure this path is correct based on your folder structure
include "../db_conn.php"; 

// 2. Check if the ID was actually sent
if (isset($_GET['id'])) {
    // Sanitize the ID to prevent SQL Injection
    $id = mysqli_real_escape_string($conn, $_GET['id']);
    
    // 3. Query the database
    $query = "SELECT * FROM products WHERE id = '$id' LIMIT 1";
    $result = mysqli_query($conn, $query);

    if ($result && mysqli_num_rows($result) > 0) {
        $product = mysqli_fetch_assoc($result);
        
        // 4. Return the product data as a JSON object
        echo json_encode($product);
    } else {
        // Return an error if product doesn't exist
        echo json_encode(['error' => 'Product not found in database.']);
    }
} else {
    // Return an error if no ID was provided in the URL
    echo json_encode(['error' => 'No product ID provided.']);
}

// Close connection
mysqli_close($conn);
?>