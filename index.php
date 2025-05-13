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
    <link rel="stylesheet" href="css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://kit.fontawesome.com/b94f65ead2.js" crossorigin="anonymous"></script>
</head>

<body>
    <?php
    // Include the header file
    include 'header.php';
    ?>

    <main>
        <h1>Track Your Calories NO</h1>
        <h1>At Any Time</h1>
        <button class="get-started">Get Started</button>

        <div class="content">
            <div class="box welcome">
                <h2>Welcome to BitBalance!</h2>
                <div class="text">
                    <p>
                        Our website makes calorie tracking simple and enjoyable. 
                        Join our community to reach your nutrition goals.
                    </p>
                </div>
            </div>
           
            <div class="box forum">
                <h2>Discussion Forum</h2>
                <div class="text">
                    <p>
                        Share tips, ask questions, and connect with others.
                    </p>
                    <p>Try it now!</p>
                </div>
            </div>

            <div class="box chart">
                <h3>Month on Month Calories</h3>
                <canvas id="calorieChart" width="400" height="250"></canvas>
            </div>
        </div>
    </main>

    <footer>
        <p></p>
    </footer>

    <script>
    const ctx = document.getElementById('calorieChart').getContext('2d');
    const calorieChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['October', 'November', 'December', 'January', 'February'],
            datasets: [{
                label: 'Consumed',
                data: [62300, 53000, 56000, 65000, 76000],
                backgroundColor: '#72A9F3'
            },
            {
                label: 'Goals',
                data: [60000, 55000, 58000, 70000, 80000],
                backgroundColor: '#FF6384'
            }
        ]
        },
        options: {
            plugins: {
                legend: {
                    display: true,
                    labels: {
                        font: {
                            weight: 'bold'
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString(); // adds commas
                        }
                    }
                }
            }
        }
    });
</script>
</body>
</html>