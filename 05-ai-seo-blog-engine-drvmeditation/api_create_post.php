<?php
// api_create_post.php - API endpoint for automated AI blog posting
header('Content-Type: application/json; charset=utf-8');

// 1. Verify Authorization Header
$headers = getallheaders();
$auth_header = isset($headers['Authorization']) ? $headers['Authorization'] : '';

if (empty($auth_header) && isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
}

$expected_token = 'Bearer drv_healing_blog_secret_2026';

if ($auth_header !== $expected_token) {
    http_response_code(401);
    echo json_encode(["success" => false, "error" => "Unauthorized"]);
    exit;
}

// 2. Read and decode JSON body
$raw_input = file_get_contents('php://input');
$data = json_decode($raw_input, true);

if (!$data || empty($data['title']) || empty($data['content'])) {
    http_response_code(400);
    echo json_encode(["success" => false, "error" => "Missing title or content"]);
    exit;
}

require_once __DIR__ . '/db.php';

try {
    $pdo = getDb();

    // 3. Format slug
    $title = trim($data['title']);
    if (!empty($data['slug'])) {
        $slug = trim($data['slug']);
    } else {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    }

    // Ensure slug uniqueness
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE slug = :slug");
    $stmt->execute([':slug' => $slug]);
    if ($stmt->fetchColumn() > 0) {
        $slug .= '-' . time();
    }

    $content = $data['content'];
    $meta_title = !empty($data['meta_title']) ? $data['meta_title'] : $title . " | Dr. V Healing";
    $meta_description = !empty($data['meta_description']) ? $data['meta_description'] : substr(strip_tags($content), 0, 160);
    $excerpt = !empty($data['excerpt']) ? $data['excerpt'] : substr(strip_tags($content), 0, 240) . '...';
    $category = !empty($data['category']) ? $data['category'] : 'Healing & Mindfulness';
    $tags = !empty($data['tags']) ? (is_array($data['tags']) ? implode(', ', $data['tags']) : $data['tags']) : 'self-healing, meditation, gratitude';
    
    // Calculate reading time (~200 words per minute)
    $word_count = str_word_count(strip_tags($content));
    $reading_time = max(1, ceil($word_count / 200));

    $faq_json = !empty($data['faq_json']) ? (is_string($data['faq_json']) ? $data['faq_json'] : json_encode($data['faq_json'])) : null;
    $author = !empty($data['author']) ? $data['author'] : 'Dr. V';

    // 4. Insert into database
    $stmt = $pdo->prepare("
        INSERT INTO posts (slug, title, meta_title, meta_description, excerpt, content, category, tags, reading_time, author, faq_json, status)
        VALUES (:slug, :title, :meta_title, :meta_description, :excerpt, :content, :category, :tags, :reading_time, :author, :faq_json, 'published')
    ");

    $stmt->execute([
        ':slug' => $slug,
        ':title' => $title,
        ':meta_title' => $meta_title,
        ':meta_description' => $meta_description,
        ':excerpt' => $excerpt,
        ':content' => $content,
        ':category' => $category,
        ':tags' => $tags,
        ':reading_time' => $reading_time,
        ':author' => $author,
        ':faq_json' => $faq_json
    ]);

    $post_id = $pdo->lastInsertId();

    // 5. Auto-update sitemap.xml
    updateSitemap($pdo);

    echo json_encode([
        "success" => true,
        "message" => "Post created successfully",
        "post" => [
            "id" => $post_id,
            "slug" => $slug,
            "url" => "https://drvmeditation.com/blog/" . $slug,
            "title" => $title
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => $e->getMessage()]);
}

function updateSitemap($pdo) {
    $sitemap_path = __DIR__ . '/sitemap.xml';
    $posts = $pdo->query("SELECT slug, updated_at FROM posts WHERE status = 'published' ORDER BY created_at DESC")->fetchAll();

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    
    // Homepage
    $xml .= "  <url>\n    <loc>https://drvmeditation.com/</loc>\n    <priority>1.0</priority>\n    <changefreq>weekly</changefreq>\n  </url>\n";
    // Blog Hub
    $xml .= "  <url>\n    <loc>https://drvmeditation.com/blog</loc>\n    <priority>0.9</priority>\n    <changefreq>daily</changefreq>\n  </url>\n";

    foreach ($posts as $p) {
        $lastmod = date('Y-m-d', strtotime($p['updated_at']));
        $xml .= "  <url>\n";
        $xml .= "    <loc>https://drvmeditation.com/blog/" . htmlspecialchars($p['slug']) . "</loc>\n";
        $xml .= "    <lastmod>{$lastmod}</lastmod>\n";
        $xml .= "    <priority>0.8</priority>\n";
        $xml .= "    <changefreq>monthly</changefreq>\n";
        $xml .= "  </url>\n";
    }

    $xml .= '</urlset>';
    @file_put_contents($sitemap_path, $xml);
}
