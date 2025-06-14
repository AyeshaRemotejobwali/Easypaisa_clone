<?php
session_start();
require_once 'db.php';

// Check if user is logged in and exists in the database
if (!isset($_SESSION['user_id'])) {
    echo "<script>window.location.href='login.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id='$user_id'";
$result = mysqli_query($conn, $sql);

if (!$result || mysqli_num_rows($result) == 0) {
    // User not found in database, destroy session and redirect to login
    session_destroy();
    echo "<script>window.location.href='login.php';</script>";
    exit();
}

$user = mysqli_fetch_assoc($result);

// Fetch transaction history
$sql = "SELECT * FROM transactions WHERE sender_id='$user_id' OR recipient_id='$user_id' ORDER BY created_at DESC";
$transactions = mysqli_query($conn, $sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Easypaisa Clone</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        body {
            background-color: #f4f4f4;
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
        .balance {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .balance h2 {
            color: #00a859;
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
            .form-container {
                margin: 10px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h1>Easypaisa Clone</h1>
        <div>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="balance">
            <h2>Wallet Balance: PKR <?php echo number_format($user['balance'], 2); ?></h2>
        </div>

        <div class="card-container">
            <div class="card" onclick="redirectTo('dashboard.php?section=transfer')">
                <h3>Money Transfer</h3>
                <p>Send money to other users or bank accounts.</p>
            </div>
            <div class="card" onclick="redirectTo('dashboard.php?section=bill')">
                <h3>Bill Payment</h3>
                <p>Pay utility bills and mobile recharges.</p>
            </div>
            <div class="card" onclick="redirectTo('dashboard.php?section=deposit')">
                <h3>Deposit</h3>
                <p>Add funds to your wallet.</p>
            </div>
            <div class="card" onclick="redirectTo('dashboard.php?section=pin')">
                <h3>Change PIN</h3>
                <p>Update your security PIN.</p>
            </div>
        </div>

        <?php if (isset($_GET['section'])): ?>
            <?php if ($_GET['section'] == 'transfer'): ?>
                <div class="form-container">
                    <h2>Money Transfer</h2>
                    <form method="POST" action="index.php">
                        <input type="text" name="recipient_phone" placeholder="Recipient Phone Number" required>
                        <input type="number" name="amount" placeholder="Amount" step="0.01" required>
                        <input type="password" name="pin" placeholder="4-digit PIN" required>
                        <button type="submit" name="transfer">Send Money</button>
                    </form>
                </div>
            <?php elseif ($_GET['section'] == 'bill'): ?>
                <div class="form-container">
                    <h2>Bill Payment</h2>
                    <form method="POST" action="index.php">
                        <select name="provider" required>
                            <option value="">Select Provider</option>
                            <option value="K-Electric">K-Electric</option>
                            <option value="SSGC">SSGC</option>
                            <option value="Telenor">Telenor</option>
                            <option value="Jazz">Jazz</option>
                        </select>
                        <input type="text" name="account_number" placeholder="Account/Reference Number" required>
                        <input type="number" name="amount" placeholder="Amount" step="0.01" required>
                        <input type="password" name="pin" placeholder="4-digit PIN" required>
                        <button type="submit" name="bill_payment">Pay Bill</button>
                    </form>
                </div>
            <?php elseif ($_GET['section'] == 'deposit'): ?>
                <div class="form-container">
                    <h2>Deposit Funds</h2>
                    <form method="POST" action="index.php">
                        <input type="number" name="amount" placeholder="Amount" step="0.01" required>
                        <button type="submit" name="deposit">Deposit</button>
                    </form>
                </div>
            <?php elseif ($_GET['section'] == 'pin'): ?>
                <div class="form-container">
                    <h2>Change PIN</h2>
                    <form method="POST" action="index.php">
                        <input type="password" name="new_pin" placeholder="New 4-digit PIN" required>
                        <button type="submit" name="update_pin">Update PIN</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="transaction-history">
            <h2>Transaction History</h2>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Details</th>
                    <th>Status</th>
                </tr>
                <?php while ($transaction = mysqli_fetch_assoc($transactions)): ?>
                    <tr>
                        <td><?php echo $transaction['created_at']; ?></td>
                        <td><?php echo ucfirst($transaction['type']); ?></td>
                        <td>PKR <?php echo number_format($transaction['amount'], 2); ?></td>
                        <td>
                            <?php
                            if ($transaction['type'] == 'transfer') {
                                if ($transaction['sender_id'] == $user_id) {
                                    $recipient_id = $transaction['recipient_id'];
                                    $sql = "SELECT name FROM users WHERE id='$recipient_id'";
                                    $recipient = mysqli_fetch_assoc(mysqli_query($conn, $sql));
                                    echo "To: " . ($recipient['name'] ?? 'Unknown');
                                } else {
                                    $sender_id = $transaction['sender_id'];
                                    $sql = "SELECT name FROM users WHERE id='$sender_id'";
                                    $sender = mysqli_fetch_assoc(mysqli_query($conn, $sql));
                                    echo "From: " . ($sender['name'] ?? 'Unknown');
                                }
                            } else {
                                echo $transaction['details'] ?? '-';
                            }
                            ?>
                        </td>
                        <td><?php echo ucfirst($transaction['status']); ?></td>
                    </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>

    <script>
        function redirectTo(page) {
            window.location.href = page;
        }
    </script>
</body>
</html>
