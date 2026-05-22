<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ChatBot College Portal</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #1e1e24;
            color: #ffffff;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            text-align: center;
        }
        .container {
            max-width: 500px;
            padding: 40px;
            background: #2a2a32;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            color: #ffffff;
        }
        p {
            color: #a0a0a8;
            font-size: 1.1rem;
            margin-bottom: 30px;
        }
        .btn-login {
            display: inline-block;
            text-decoration: none;
            background-color: #007bff;
            color: white;
            padding: 12px 30px;
            font-size: 1rem;
            font-weight: 600;
            border-radius: 5px;
            transition: background 0.2s ease-in-out;
        }
        .btn-login:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>

    <div class="container">
        <h1>ChatBot Portal</h1>
        <p>Mirëseerdhët në sistemin e menaxhimit të ChatBot-it për Kolegjin.</p>
        
        <a href="login.php" class="btn-login">Kyqu në Dashboard</a>
    </div>

</body>
</html>