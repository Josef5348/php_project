<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload'])) {
        $description = trim($_POST['description']);
        $image = file_get_contents($_FILES['photo']['tmp_name']);

        if (!empty($image) && !empty($description)) {
            $stmt = $pdo->prepare("INSERT INTO photos (user_id, image, description) VALUES (?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $image, $description]);
            header("Location: index.php");
            exit();
        }
    }

    // Handle comments
    if (isset($_POST['submit_comment'])) {
        $comment = trim($_POST['comment']);
        $photo_id = $_POST['photo_id'];

        if (!empty($comment) && !empty($photo_id)) {
            $stmt = $pdo->prepare("INSERT INTO comments (photo_id, user_id, comment) VALUES (?, ?, ?)");
            $stmt->execute([$photo_id, $_SESSION['user_id'], $comment]);
            header("Location: index.php");
            exit();
        }
    }

    // Handle voting
    if (isset($_POST['vote'])) {
        $photo_id = $_POST['photo_id'];
        $vote = $_POST['vote']; // 1 for upvote, -1 for downvote

        // Check if the user has already voted
        $stmt = $pdo->prepare("SELECT * FROM votes WHERE photo_id = ? AND user_id = ?");
        $stmt->execute([$photo_id, $_SESSION['user_id']]);
        $existing_vote = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing_vote) {
            // Update the existing vote
            $stmt = $pdo->prepare("UPDATE votes SET vote = ? WHERE photo_id = ? AND user_id = ?");
            $stmt->execute([$vote, $photo_id, $_SESSION['user_id']]);
        } else {
            // Insert a new vote
            $stmt = $pdo->prepare("INSERT INTO votes (photo_id, user_id, vote) VALUES (?, ?, ?)");
            $stmt->execute([$photo_id, $_SESSION['user_id'], $vote]);
        }

        header("Location: index.php");
        exit();
    }
}

// Fetch photos with vote counts, ordered by vote_count descending
$photos = $pdo->query("
    SELECT photos.*, users.username, 
           COALESCE(SUM(votes.vote), 0) AS vote_count
    FROM photos
    LEFT JOIN users ON photos.user_id = users.id
    LEFT JOIN votes ON photos.id = votes.photo_id
    GROUP BY photos.id
    ORDER BY vote_count DESC, created_at ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" type="text/css" href="style.css">
    <title>Photo Sharing App</title>
</head>
<body>
<h1>Welcome, <?= htmlspecialchars($_SESSION['username']) ?>!</h1>
<a href="logout.php">Logout</a>

<h2>Upload Photo</h2>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="photo" required>
    <textarea name="description" placeholder="Description"></textarea>
    <button type="submit" name="upload">Upload</button>
</form>

<h2>Photos</h2>
<?php if (!empty($photos)): ?>
    <div>
        <h3>First Place Photo</h3>
        <img src="data:image/jpeg;base64,<?= base64_encode($photos[0]['image']) ?>" alt="First Place" style="max-width: 300px; border: 5px solid gold;">
        <p><?= htmlspecialchars($photos[0]['description']) ?></p>
        <p>Uploaded by <?= htmlspecialchars($photos[0]['username']) ?> on <?= $photos[0]['created_at'] ?></p>
        <h4>Votes: <?= $photos[0]['vote_count'] ?></h4>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="photo_id" value="<?= $photos[0]['id'] ?>">
            <button type="submit" name="vote" value="1">Upvote</button>
        </form>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="photo_id" value="<?= $photos[0]['id'] ?>">
            <button type="submit" name="vote" value="-1">Downvote</button>
        </form>

        <h4>Comments:</h4>
        <?php
        // Fetch comments for the first-place photo
        $stmt = $pdo->prepare("SELECT comments.*, users.username FROM comments 
                               JOIN users ON comments.user_id = users.id 
                               WHERE photo_id = ?");
        $stmt->execute([$photos[0]['id']]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($comments as $comment): ?>
            <p><strong><?= htmlspecialchars($comment['username']) ?>:</strong> <?= htmlspecialchars($comment['comment']) ?></p>
        <?php endforeach; ?>

        <form method="POST">
            <textarea name="comment" placeholder="Leave a comment..." required></textarea>
            <input type="hidden" name="photo_id" value="<?= $photos[0]['id'] ?>">
            <button type="submit" name="submit_comment">Submit</button>
        </form>
    </div>
    <hr>
<?php endif; ?>

<?php foreach ($photos as $index => $photo): ?>
    <?php if ($index === 0) continue; // Skip the first-place photo ?>
    <div>
        <img src="data:image/jpeg;base64,<?= base64_encode($photo['image']) ?>" alt="Photo" style="max-width: 300px;">
        <p><?= htmlspecialchars($photo['description']) ?></p>
        <p>Uploaded by <?= htmlspecialchars($photo['username']) ?> on <?= $photo['created_at'] ?></p>
        
        <h4>Votes: <?= $photo['vote_count'] ?></h4>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
            <button type="submit" name="vote" value="1">Upvote</button>
        </form>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
            <button type="submit" name="vote" value="-1">Downvote</button>
        </form>

        <h4>Comments:</h4>
        <?php
        $stmt = $pdo->prepare("SELECT comments.*, users.username FROM comments 
                               JOIN users ON comments.user_id = users.id 
                               WHERE photo_id = ?");
        $stmt->execute([$photo['id']]);
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($comments as $comment): ?>
            <p><strong><?= htmlspecialchars($comment['username']) ?>:</strong> <?= htmlspecialchars($comment['comment']) ?></p>
        <?php endforeach; ?>

        <form method="POST">
            <textarea name="comment" placeholder="Leave a comment..." required></textarea>
            <input type="hidden" name="photo_id" value="<?= $photo['id'] ?>">
            <button type="submit" name="submit_comment">Submit</button>
        </form>
    </div>
<?php endforeach; ?>
</body>
</html>
