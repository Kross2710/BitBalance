<?php
session_start(); // Start the session

// Simulate user login
if (!isset($_SESSION['user'])) {
    // $_SESSION['user'] = ['firstname' => 'Alice'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BitBalance</title>
    <link rel="stylesheet" href="css/products.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>
<body>
    <?php
    // Include the header file
    include 'header.php';
    ?>  
  <main>
    <h1 class="title">Shop Our Products</h1>
    <div class="products">
      <div class="product">
        <img src="images/shaker.png" alt="Protein Shaker">
        <h2>Protein Shaker</h2>
        <p>$80</p>
        <button class="add-to-cart">Add to Cart</button>
      </div>
      <div class="product">
        <img src="images/protein.png" alt="Protein Powder">
        <h2>Protein Powder</h2>
        <p>$60</p>
        <button class="add-to-cart">Add to Cart</button>
      </div>
      <div class="product">
        <img src="images/creatine.png" alt="Creatine">
        <h2>Creatine</h2>
        <p>$100</p>
        <button class="add-to-cart">Add to Cart</button>
      </div>
      <div class="product">
        <img src="images/resis.png" alt="Resistance Bands">
        <h2>Resistance Bands</h2>
        <p>$70</p>
        <button class="add-to-cart">Add to Cart</button>
      </div>
      <div class="product">
        <img src="images/pre.png" alt="Pre Workout">
        <h2>Pre Workout</h2>
        <p>$60</p>
        <button class="add-to-cart">Add to Cart</button>
      </div>
      <div class="product">
        <img src="images/dumbbell.png" alt="Dumbbell">
        <h2>Dumbbell</h2>
        <p>$100</p>
        <button class="add-to-cart">Add to Cart</button>
      </div>
    </div>
  </main>
</body>
</html>
