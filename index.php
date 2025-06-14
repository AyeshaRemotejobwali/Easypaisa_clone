<?php
session_start();
require_once 'db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !in_array(basename($_SERVER['PHP_SELF']), ['index.php', 'signup.php', 'login.php'])) {
    echo "<script>window.location.href='index.php';</script>";
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['signup'])) {
        // Sanitize and validate input
        $name = filter_var($_POST['name'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $phone = filter_var($_POST['phone'], FILTER_SANITIZE_STRING);
        $password = $_POST['password'];
        $pin = $_POST['pin'];

        // Basic input validation
        if (empty($name) || empty($email) || empty($phone) || empty($password) || empty($pin)) {
            echo "<script>alert('All fields are required!');</script>";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo "<script>alert('Invalid email format!');</script>";
        } elseif (strlen($pin) != 4 || !ctype_digit($pin)) {
            echo "<script>alert('PIN must be a 4-digit number!');</script>";
        } else {
            // Check if email or phone already exists
            $email = mysqli_real_escape_string($conn, $email);
            $phone = mysqli_real_escape_string($conn, $phone);
            $check_sql = "SELECT id FROM users WHERE email='$email' OR phone='$phone'";
            $check_result = mysqli_query($conn, $check_sql);

            if (!$check_result) {
                echo "<script>alert('Database error: " . mysqli_error($conn) . "');</script>";
            } elseif (mysqli_num_rows($check_result) > 0) {
                echo "<script>alert('Email or phone number already registered!');</script>";
            } else {
                // Hash password and PIN
                $password = password_hash($password, PASSWORD_BCRYPT);
                $pin = password_hash($pin, PASSWORD_BCRYPT);
                $name = mysqli_real_escape_string($conn, $name);

                // Insert user into database
                $sql = "INSERT INTO users (name, email, phone, password, pin) VALUES ('$name', '$email', '$phone', '$password', '$pin')";
                if (mysqli_query($conn, $sql)) {
                    echo "<script>alert('Registration successful! Please login.'); window.location.href='login.php';</script>";
                } else {
                    echo "<script>alert('Error during registration: " . mysqli_error($conn) . "');</script>";
                }
            }
        }
    } elseif (isset($_POST['login'])) {
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        $password = $_POST['password'];
        $pin = $_POST['pin'];

        $sql = "SELECT * FROM users WHERE email='$email'";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            echo "<script>alert('Database error: " . mysqli_error($conn) . "');</script>";
        } elseif (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            if (password_verify($password, $user['password']) && password_verify($pin, $user['pin'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['name'] = $user['name'];
                echo "<script>window.location.href='dashboard.php';</script>";
            } else {
                echo "<script>alert('Invalid credentials or PIN');</script>";
            }
        } else {
            echo "<script>alert('User not found');</script>";
        }
    } elseif (isset($_POST['transfer'])) {
        $recipient_phone = mysqli_real_escape_string($conn, $_POST['recipient_phone']);
        $amount = floatval($_POST['amount']);
        $pin = $_POST['pin'];
        $user_id = $_SESSION['user_id'];

        $sql = "SELECT * FROM users WHERE id='$user_id'";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            echo "<script>alert('Database error: " . mysqli_error($conn) . "');</script>";
        } else {
            $user = mysqli_fetch_assoc($result);
            if (password_verify($pin, $user['pin'])) {
                if ($user['balance'] >= $amount) {
                    $sql = "SELECT * FROM users WHERE phone='$recipient_phone'";
                    $result = mysqli_query($conn, $sql);
                    if (mysqli_num_rows($result) > 0) {
                        $recipient = mysqli_fetch_assoc($result);
                        $recipient_id = $recipient['id'];

                        // Update balances
                        $sql = "UPDATE users SET balance=balance-'$amount' WHERE id='$user_id'";
                        mysqli_query($conn, $sql);
                        $sql = "UPDATE users SET balance=balance+'$amount' WHERE id='$recipient_id'";
                        mysqli_query($conn, $sql);

                        // Record transaction
                        $sql = "INSERT INTO transactions (sender_id, recipient_id, amount, type, status) VALUES ('$user_id', '$recipient_id', '$amount', 'transfer', 'completed')";
                        mysqli_query($conn, $sql);

                        echo "<script>alert('Transfer successful!'); window.location.href='dashboard.php';</script>";
                    } else {
                        echo "<script>alert('Recipient not found');</script>";
                    }
                } else {
                    echo "<script>alert('Insufficient balance');</script>";
                }
            } else {
                echo "<script>alert('Invalid PIN');</script>";
            }
        }
    } elseif (isset($_POST['bill_payment'])) {
        $provider = mysqli_real_escape_string($conn, $_POST['provider']);
        $account_number = mysqli_real_escape_string($conn, $_POST['account_number']);
        $amount = floatval($_POST['amount']);
        $pin = $_POST['pin'];
        $user_id = $_SESSION['user_id'];

        $sql = "SELECT * FROM users WHERE id='$user_id'";
        $result = mysqli_query($conn, $sql);
        if (!$result) {
            echo "<script>alert('Database error: " . mysqli_error($conn) . "');</script>";
        } else {
            $user = mysqli_fetch_assoc($result);
            if (password_verify($pin, $user['pin'])) {
                if ($user['balance'] >= $amount) {
                    $sql = "UPDATE users SET balance=balance-'$amount' WHERE id='$user_id'";
                    mysqli_query($conn, $sql);

                    $sql = "INSERT INTO transactions (sender_id, recipient_id, amount, type, status, details) VALUES ('$user_id', NULL, '$amount', 'bill_payment', 'completed', '$provider - $account_number')";
                    mysqli_query($conn, $sql);

                    echo "<script>alert('Bill payment successful!'); window.location.href='dashboard.php';</script>";
                } else {
                    echo "<script>alert('Insufficient balance');</script>";
                }
            } else {
                echo "<script>alert('Invalid PIN');</script>";
            }
        }
    } elseif (isset($_POST['deposit'])) {
        $amount = floatval($_POST['amount']);
        $user_id = $_SESSION['user_id'];

        $sql = "UPDATE users SET balance=balance+'$amount' WHERE id='$user_id'";
        if (mysqli_query($conn, $sql)) {
            $sql = "INSERT INTO transactions (sender_id, recipient_id, amount, type, status) VALUES ('$user_id', NULL, '$amount', 'deposit', 'completed')";
            mysqli_query($conn, $sql);
            echo "<script>alert('Deposit successful!'); window.location.href='dashboard.php';</script>";
        } else {
            echo "<script>alert('Error during deposit: " . mysqli_error($conn) . "');</script>";
        }
    } elseif (isset($_POST['update_pin'])) {
        $new_pin = password_hash($_POST['new_pin'], PASSWORD_BCRYPT);
        $user_id = $_SESSION['user_id'];

        $sql = "UPDATE users SET pin='$new_pin' WHERE id='$user_id'";
        if (mysqli_query($conn, $sql)) {
            echo "<script>alert('PIN updated successfully!'); window.location.href='dashboard.php';</script>";
        } else {
            echo "<script>alert('Error updating PIN: " . mysqli_error($conn) . "');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Easypaisa Clone</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        body {
            background-color: #f4f4f4;
            color: #333;
        }
        .navbar {
            background: linear-gradient(90deg, #00a859, #008a47);
            padding: 15px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h1 {
            font-size: 24px;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            font-size: 16px;
        }
        .navbar a:hover {
            text-decoration: underline;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
        }
        .hero {
            background: url('https://images.unsplash.com/photo-1556740714-7a6a1a0a8b1f') no-repeat center center/cover;
            padding: 50px;
            text-align: center;
            color: white;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .hero h2 {
            font-size: 36px;
            margin-bottom: 10px;
        }
        .hero p {
            font-size: 18px;
        }
        .card-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
        }
        .card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            width: 300px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card h3 {
            color: #00a859;
            margin-bottom: 10px;
        }
        .card p {
            color: #666;
        }
        .form-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 20px auto;
        }
        .form-container h2 {
            color: #00a859;
            margin-bottom: 20px;
            text-align: center;
        }
        .form-container input,
        .form-container select {
            width: 100%;
            padding: 10px;
            margin: 10px 0;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .form-container button {
            width: 100%;
            padding: 10px;
            background: #00a859;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .form-container button:hover {
            background: #008a47;
        }
        .transaction-history {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
        .transaction-history table {
            width: 100%;
            border-collapse: collapse;
        }
        .transaction-history th,
        .transaction-history td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .transaction-history th {
            background: #00a859;
            color: white;
        }
        @media (max-width: 768px) {
            .card {
                width: 100%;
            }
            .hero {
                padding: 30px;
            }
            .hero h2 {
                font-size: 24px;
            }
            .hero p {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Easypaisa Clone</h1>
        <div>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="dashboard.php">Dashboard</a>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="signup.php">Sign Up</a>
                <a href="login.php">Login</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="container">
        <div class="hero">
            <h2>Welcome to Easypaisa Clone</h2>
            <p>Send, receive, and manage your money with ease and security.</p>
        </div>

        <div class="card-container">
            <div class="card">
                <h3>Money Transfer</h3>
                <p>Send money to friends, family, or bank accounts instantly.</p>
            </div>
            <div class="card">
                <h3>Bill Payments</h3>
                <p>Pay utility bills, mobile recharges, and subscriptions.</p>
            </div>
            <div class="card">
                <h3>Digital Wallet</h3>
                <p>Securely store and manage your digital currency.</p>
            </div>
        </div>
    </div>

    <script>
        // JavaScript for redirection
        function redirectTo(page) {
            window.location.href = page;
        }
    </script>
</body>
</html>
