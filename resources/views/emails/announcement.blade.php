<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement - Student Tracker</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f9f9f9;
            padding: 30px;
            border-radius: 10px;
            border: 1px solid #ddd;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            color: #2c3e50;
            margin: 0;
        }
        .content {
            background-color: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        .message {
            color: #555;
            white-space: pre-wrap;
            margin-bottom: 20px;
        }
        .footer {
            text-align: center;
            color: #7f8c8d;
            font-size: 12px;
            margin-top: 20px;
        }
        .info {
            background-color: #e8f4f8;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin-top: 20px;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Student Tracker</h1>
        </div>
        
        <div class="content">
            <div class="title">{{ $announcement->title }}</div>
            
            <div class="message">{{ $announcement->message }}</div>
            
            <div class="info">
                <strong>📢 Important Announcement</strong><br>
                This message was sent by Student Tracker Administrator.
            </div>
        </div>
        
        <div class="footer">
            <p>This email was sent by Student Tracker Administrator</p>
            <p>If you have any questions, please contact support.</p>
        </div>
    </div>
</body>
</html>

