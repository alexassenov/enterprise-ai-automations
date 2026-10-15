<?php
// post.php - Dr. V Single Article Page with Schema.org & SEO Best Practices
require_once __DIR__ . '/db.php';

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';

if (empty($slug)) {
    header("Location: /blog");
    exit;
}

try {
    $pdo = getDb();
    
    // Fetch post
    $stmt = $pdo->prepare("SELECT * FROM posts WHERE slug = :slug AND status = 'published' LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    $post = $stmt->fetch();

    if (!$post) {
        http_response_code(404);
        echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>404 - Article Not Found</title><script src='https://cdn.tailwindcss.com'></script></head><body class='bg-slate-50 flex items-center justify-center min-h-screen text-center p-6'><div><h1 class='text-4xl font-bold mb-4'>404 - Article Not Found</h1><p class='text-gray-600 mb-6'>The insight you are looking for does not exist or has been relocated.</p><a href='/blog' class='px-6 py-3 bg-teal-600 text-white rounded-full'>Return to Blog</a></div></body></html>";
        exit;
    }

    // Increment views counter
    $pdo->prepare("UPDATE posts SET views = views + 1 WHERE id = :id")->execute([':id' => $post['id']]);

    // Parse FAQ JSON if available
    $faqs = [];
    if (!empty($post['faq_json'])) {
        $faqs = json_decode($post['faq_json'], true) ?: [];
    }

    // Fetch 2 related articles
    $stmt_related = $pdo->prepare("SELECT slug, title, reading_time FROM posts WHERE id != :id AND status = 'published' ORDER BY created_at DESC LIMIT 2");
    $stmt_related->execute([':id' => $post['id']]);
    $related_posts = $stmt_related->fetchAll();

} catch (Exception $e) {
    http_response_code(500);
    die("Database Error");
}

$page_title = !empty($post['meta_title']) ? htmlspecialchars($post['meta_title']) : htmlspecialchars($post['title']) . " | Dr. V Healing";
$meta_desc = !empty($post['meta_description']) ? htmlspecialchars($post['meta_description']) : htmlspecialchars(substr(strip_tags($post['content']), 0, 160));
$canonical_url = "https://drvmeditation.com/blog/" . htmlspecialchars($post['slug']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?></title>
  <meta name="description" content="<?= $meta_desc ?>">
  <link rel="canonical" href="<?= $canonical_url ?>">

  <!-- Open Graph -->
  <meta property="og:title" content="<?= $page_title ?>">
  <meta property="og:description" content="<?= $meta_desc ?>">
  <meta property="og:type" content="article">
  <meta property="og:url" content="<?= $canonical_url ?>">
  <meta property="og:image" content="https://drvmeditation.com/book_self_healing_cover.jpg">

  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <!-- Schema.org JSON-LD -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "BlogPosting",
    "mainEntityOfPage": {
      "@type": "WebPage",
      "@id": "<?= $canonical_url ?>"
    },
    "headline": "<?= addslashes($post['title']) ?>",
    "description": "<?= addslashes($meta_desc) ?>",
    "image": "https://drvmeditation.com/book_self_healing_cover.jpg",
    "author": {
      "@type": "Person",
      "name": "Dr. V",
      "jobTitle": "Medical Doctor & Mindfulness Teacher",
      "url": "https://drvmeditation.com/#about"
    },
    "publisher": {
      "@type": "Organization",
      "name": "Dr. V Healing",
      "logo": {
        "@type": "ImageObject",
        "url": "https://drvmeditation.com/B92D3EE7-9532-4914-99AC-19082410B5D1.png"
      }
    },
    "datePublished": "<?= date('c', strtotime($post['created_at'])) ?>",
    "dateModified": "<?= date('c', strtotime($post['updated_at'])) ?>"
  }
  </script>

  <?php if (!empty($faqs)): ?>
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
      <?php 
      $faq_items = [];
      foreach ($faqs as $f) {
          $q = addslashes($f['q'] ?? ($f['question'] ?? ''));
          $a = addslashes($f['a'] ?? ($f['answer'] ?? ''));
          if ($q && $a) {
              $faq_items[] = "{\n        \"@type\": \"Question\",\n        \"name\": \"$q\",\n        \"acceptedAnswer\": {\n          \"@type\": \"Answer\",\n          \"text\": \"$a\"\n        }\n      }";
          }
      }
      echo implode(",\n      ", $faq_items);
      ?>
    ]
  }
  </script>
  <?php endif; ?>

  <style>
    :root {
      --brand-purple: #7272BB;
      --brand-teal:   #009D8A;
      --text-body:    #595959;
      --text-dark:    #333333;
    }
    body { font-family: 'Roboto', sans-serif; color: var(--text-body); background: #fbfbfd; line-height: 1.8; }
    h1, h2, h3, h4 { font-family: 'Montserrat', sans-serif; color: var(--text-dark); }
    .article-content h2 { font-size: 1.65rem; font-weight: 800; margin-top: 2.5rem; margin-bottom: 1rem; color: #222; border-bottom: 2px solid rgba(114,114,187,0.1); padding-bottom: 0.5rem; }
    .article-content h3 { font-size: 1.3rem; font-weight: 700; margin-top: 1.8rem; margin-bottom: 0.75rem; color: var(--brand-purple); }
    .article-content p { margin-bottom: 1.35rem; font-size: 1.05rem; font-weight: 300; }
    .article-content ul, .article-content ol { margin-bottom: 1.5rem; padding-left: 1.5rem; }
    .article-content li { margin-bottom: 0.5rem; }
    .article-content blockquote { border-left: 4px solid var(--brand-teal); padding-left: 1.25rem; font-style: italic; color: #444; background: rgba(0,157,138,0.04); padding-top: 0.8rem; padding-bottom: 0.8rem; margin: 2rem 0; border-radius: 0 12px 12px 0; }
  </style>
</head>
<body class="min-h-screen flex flex-col justify-between">

  <!-- Nav -->
  <nav class="bg-white/95 backdrop-blur border-b border-purple-100 sticky top-0 z-50">
    <div class="max-w-5xl mx-auto px-6 py-4 flex items-center justify-between">
      <a href="/" class="flex items-center gap-3 no-underline">
        <img src="/B92D3EE7-9532-4914-99AC-19082410B5D1.png" alt="Dr. V Logo" class="w-10 h-10 rounded-full object-cover shadow">
        <div>
          <span class="block font-bold text-lg leading-tight" style="font-family:'Montserrat',sans-serif;color:#404040;">Dr. V <span style="color:var(--brand-teal)">Healing</span></span>
          <span class="block text-xs tracking-widest uppercase" style="color:var(--brand-purple);font-family:'Montserrat',sans-serif;font-weight:500;">Mindfulness · Recovery · Life</span>
        </div>
      </a>
      <div class="flex items-center gap-6">
        <a href="/blog" class="text-sm font-semibold hover:text-purple-600" style="color:var(--brand-purple);">← All Articles</a>
        <a href="/#app" class="px-5 py-2.5 rounded-full text-xs font-bold text-white shadow-md hover:bg-teal-700 transition" style="background:var(--brand-teal);font-family:'Montserrat',sans-serif;">Get the App</a>
      </div>
    </div>
  </nav>

  <!-- Article Container -->
  <main class="max-w-4xl mx-auto px-6 py-12 flex-grow w-full">
    
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-xs text-gray-400 mb-6 font-medium">
      <a href="/" class="hover:text-gray-600">Home</a>
      <span>/</span>
      <a href="/blog" class="hover:text-gray-600">Blog</a>
      <span>/</span>
      <span class="text-gray-600 truncate max-w-xs"><?= htmlspecialchars($post['category']) ?></span>
    </div>

    <!-- Article Header -->
    <header class="mb-10 pb-8 border-b border-gray-100">
      <span class="inline-block px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider mb-4" style="background:rgba(114,114,187,0.1);color:var(--brand-purple);">
        <?= htmlspecialchars($post['category']) ?>
      </span>
      <h1 class="text-3xl sm:text-4xl md:text-5xl font-extrabold leading-tight text-gray-900 mb-6">
        <?= htmlspecialchars($post['title']) ?>
      </h1>
      
      <div class="flex flex-wrap items-center justify-between gap-4 text-xs text-gray-500 mb-6">
        <div class="flex items-center gap-3">
          <img src="/B92D3EE7-9532-4914-99AC-19082410B5D1.png" alt="Dr. V" class="w-10 h-10 rounded-full object-cover border border-purple-200">
          <div>
            <div class="font-bold text-gray-800 text-sm">Dr. V</div>
            <div>Medical Doctor & Author of The Three Pillars</div>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <span>📅 <?= date('F j, Y', strtotime($post['created_at'])) ?></span>
          <span>⏱️ <?= $post['reading_time'] ?> min read</span>
        </div>
      </div>

      <?php if (!empty($post['image_url'])): ?>
      <div class="rounded-3xl overflow-hidden shadow-lg mb-8 max-h-[460px]">
        <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover" loading="eager">
      </div>
      <?php endif; ?>
    </header>

    <!-- Post Body -->
    <article class="article-content text-gray-700">
      <?= $post['content'] ?>
    </article>

    <!-- FAQs if available -->
    <?php if (!empty($faqs)): ?>
    <section class="mt-14 pt-8 border-t border-gray-100">
      <h3 class="text-2xl font-bold mb-6 text-gray-900">Frequently Asked Questions</h3>
      <div class="space-y-4">
        <?php foreach ($faqs as $f): 
          $q = $f['q'] ?? ($f['question'] ?? '');
          $a = $f['a'] ?? ($f['answer'] ?? '');
          if ($q && $a): ?>
            <div class="bg-white p-6 rounded-2xl border border-purple-50 shadow-sm">
              <h4 class="font-bold text-base text-gray-800 mb-2">❓ <?= htmlspecialchars($q) ?></h4>
              <p class="text-sm text-gray-600 font-light leading-relaxed"><?= htmlspecialchars($a) ?></p>
            </div>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- Embedded App CTA -->
    <div class="my-16 p-8 sm:p-10 rounded-3xl bg-gradient-to-r from-purple-50 via-teal-50 to-indigo-50 border border-teal-100/60 shadow-lg text-center">
      <div class="w-16 h-16 rounded-2xl bg-white shadow-md flex items-center justify-center mx-auto mb-4 text-3xl">🌿</div>
      <h3 class="text-2xl font-bold mb-3 text-gray-900">Put These Principles Into Practice</h3>
      <p class="text-sm text-gray-600 max-w-xl mx-auto mb-6 font-light leading-relaxed">
        Reading about recovery is the first step — practicing it every morning is what rewires the brain. Start with guided timers and meditations in the official <strong>Dr. V Healing App</strong>.
      </p>
      <div class="flex flex-wrap items-center justify-center gap-4">
        <a href="https://apps.apple.com/ca/app/dr-v-healing/id6757068416" target="_blank" rel="noopener" class="px-6 py-3 rounded-full text-xs font-bold text-white shadow hover:opacity-90 transition flex items-center gap-2" style="background:var(--brand-purple);">
          🍎 Download on App Store
        </a>
        <a href="https://play.google.com/store/apps/details?id=com.drvhealing.app" target="_blank" rel="noopener" class="px-6 py-3 rounded-full text-xs font-bold text-white shadow hover:opacity-90 transition flex items-center gap-2" style="background:var(--brand-teal);">
          🤖 Get on Google Play
        </a>
      </div>
    </div>

    <!-- Related Articles -->
    <?php if (!empty($related_posts)): ?>
    <div class="mt-12 pt-8 border-t border-gray-100">
      <h3 class="text-lg font-bold text-gray-800 mb-4">Related Insights</h3>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <?php foreach ($related_posts as $rel): ?>
          <a href="/blog/<?= htmlspecialchars($rel['slug']) ?>" class="p-4 bg-white rounded-xl border border-gray-100 shadow-sm hover:border-purple-200 transition block no-underline text-inherit">
            <span class="text-xs text-gray-400 block mb-1">⏱️ <?= $rel['reading_time'] ?> min read</span>
            <span class="font-bold text-sm text-gray-800 hover:text-purple-600"><?= htmlspecialchars($rel['title']) ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </main>

  <!-- Footer -->
  <footer class="bg-slate-900 text-gray-400 py-10 px-6 border-t border-slate-800 text-xs text-center">
    <div class="max-w-5xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
      <p>© 2026 Dr. V Healing & Meditation. All Rights Reserved.</p>
      <div class="flex gap-6">
        <a href="/" class="hover:text-white">Home</a>
        <a href="/blog" class="hover:text-white">Blog</a>
        <a href="/#app" class="hover:text-white">App</a>
        <a href="/#books" class="hover:text-white">Books</a>
      </div>
    </div>
  </footer>

</body>
</html>
