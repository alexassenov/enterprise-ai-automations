<?php
// blog.php - Dr. V Healing Blog & Insights Hub
require_once __DIR__ . '/db.php';

try {
    $pdo = getDb();
    
    // Category filter
    $selected_cat = isset($_GET['cat']) ? trim($_GET['cat']) : '';
    $search = isset($_GET['q']) ? trim($_GET['q']) : '';

    $sql = "SELECT id, slug, title, excerpt, category, image_url, reading_time, created_at FROM posts WHERE status = 'published'";
    $params = [];

    if (!empty($selected_cat)) {
        $sql .= " AND category = :cat";
        $params[':cat'] = $selected_cat;
    }

    if (!empty($search)) {
        $sql .= " AND (title LIKE :q OR content LIKE :q)";
        $params[':q'] = '%' . $search . '%';
    }

    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    // Fetch distinct categories
    $categories = $pdo->query("SELECT DISTINCT category FROM posts WHERE status = 'published'")->fetchAll(PDO::FETCH_COLUMN);

} catch (Exception $e) {
    $posts = [];
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Healing Insights & Wisdom | Dr. V Meditation & Healing Blog</title>
  <meta name="description" content="Explore evidence-based articles on mindfulness, chronic pain recovery, guided meditation, and the Three Pillars of Self-Healing by Dr. V.">
  <link rel="canonical" href="https://drvmeditation.com/blog">
  
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Montserrat:wght@400;500;600;700;800&display=swap" rel="stylesheet">

  <style>
    :root {
      --brand-purple: #7272BB;
      --brand-teal:   #009D8A;
      --text-body:    #595959;
      --text-dark:    #404040;
    }
    body { font-family: 'Roboto', sans-serif; color: var(--text-body); background: #fbfbfd; }
    h1, h2, h3, h4 { font-family: 'Montserrat', sans-serif; color: var(--text-dark); }
    .btn-primary {
      background: var(--brand-teal);
      color: #fff;
      font-family: 'Montserrat', sans-serif;
      font-weight: 600;
      border-radius: 50px;
      padding: 0.75rem 1.8rem;
      transition: all 0.2s;
    }
    .btn-primary:hover { background: #007f6e; transform: translateY(-2px); }
  </style>
</head>
<body class="min-h-screen flex flex-col justify-between">

  <!-- Nav -->
  <nav class="bg-white/95 backdrop-blur border-b border-purple-100 sticky top-0 z-50">
    <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
      <a href="/" class="flex items-center gap-3 no-underline">
        <img src="/B92D3EE7-9532-4914-99AC-19082410B5D1.png" alt="Dr. V Logo" class="w-10 h-10 rounded-full object-cover shadow">
        <div>
          <span class="block font-bold text-lg leading-tight" style="font-family:'Montserrat',sans-serif;color:#404040;">Dr. V <span style="color:var(--brand-teal)">Healing</span></span>
          <span class="block text-xs tracking-widest uppercase" style="color:var(--brand-purple);font-family:'Montserrat',sans-serif;font-weight:500;">Mindfulness · Recovery · Life</span>
        </div>
      </a>
      <div class="flex items-center gap-6">
        <a href="/" class="text-sm font-medium hover:text-purple-600">Home</a>
        <a href="/#pillars" class="text-sm font-medium hover:text-purple-600 hidden sm:inline">Three Pillars</a>
        <a href="/#books" class="text-sm font-medium hover:text-purple-600 hidden sm:inline">Books</a>
        <a href="/#quiz" class="text-sm font-medium hover:text-purple-600 hidden sm:inline">Quiz</a>
        <a href="/#app" class="btn-primary text-xs">Get the App →</a>
      </div>
    </div>
  </nav>

  <!-- Hero Header -->
  <header class="py-16 px-6 text-center" style="background:linear-gradient(135deg, #f7f5ff 0%, #edfbf9 50%, #f0f8ff 100%);">
    <div class="max-w-4xl mx-auto">
      <span class="text-xs font-bold uppercase tracking-widest block mb-2" style="color:var(--brand-teal);font-family:'Montserrat',sans-serif;">Healing Intelligence & Knowledge</span>
      <h1 class="text-3xl sm:text-5xl font-extrabold mb-4" style="color:#333;">Evidence-Based Wisdom & <span style="color:var(--brand-purple)">Self-Healing</span></h1>
      <p class="text-base sm:text-lg text-gray-600 max-w-2xl mx-auto font-light leading-relaxed mb-8">
        Practical articles on chronic pain reduction, neuroplasticity, guided visualization, and building daily mindfulness rituals.
      </p>

      <!-- Category Filter Pills -->
      <div class="flex flex-wrap items-center justify-center gap-2">
        <a href="/blog" class="px-4 py-2 rounded-full text-xs font-semibold transition <?= empty($selected_cat) ? 'bg-purple-600 text-white shadow-md' : 'bg-white text-gray-700 hover:bg-purple-50 border border-gray-200' ?>">All Articles</a>
        <?php foreach ($categories as $cat): ?>
          <a href="/blog?cat=<?= urlencode($cat) ?>" class="px-4 py-2 rounded-full text-xs font-semibold transition <?= $selected_cat === $cat ? 'bg-purple-600 text-white shadow-md' : 'bg-white text-gray-700 hover:bg-purple-50 border border-gray-200' ?>">
            <?= htmlspecialchars($cat) ?>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </header>

  <!-- Blog Grid -->
  <main class="max-w-6xl mx-auto px-6 py-16 flex-grow w-full">
    <?php if (empty($posts)): ?>
      <div class="text-center py-20 bg-white rounded-3xl border border-gray-100 shadow-sm max-w-md mx-auto">
        <div class="text-5xl mb-4">🌿</div>
        <h3 class="text-xl font-bold mb-2">No Articles Yet</h3>
        <p class="text-sm text-gray-500 mb-6">Our automated healing intelligence pipeline is currently preparing fresh insights.</p>
        <a href="/" class="btn-primary text-sm inline-block">Return to Homepage</a>
      </div>
    <?php else: ?>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach ($posts as $idx => $post): ?>
          <article class="bg-white rounded-2xl overflow-hidden border border-purple-50/80 shadow-sm hover:shadow-xl hover:-translate-y-1 transition duration-200 flex flex-col justify-between">
            <div>
              <!-- Visual Card Header -->
              <?php if (!empty($post['image_url'])): ?>
                <div class="h-48 overflow-hidden relative">
                  <img src="<?= htmlspecialchars($post['image_url']) ?>" alt="<?= htmlspecialchars($post['title']) ?>" class="w-full h-full object-cover transition duration-300 hover:scale-105" loading="lazy">
                  <span class="absolute top-3 left-3 bg-white/90 backdrop-blur px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider shadow" style="color:var(--brand-purple);font-family:'Montserrat',sans-serif;">
                    <?= htmlspecialchars($post['category']) ?>
                  </span>
                </div>
              <?php else: ?>
                <div class="h-44 p-6 flex items-center justify-center text-center relative overflow-hidden" style="background:linear-gradient(135deg, <?= $idx % 2 == 0 ? 'rgba(114,114,187,0.12), rgba(0,157,138,0.1)' : 'rgba(0,157,138,0.12), rgba(114,114,187,0.1)' ?>);">
                  <div class="relative z-10">
                    <span class="text-4xl block mb-2"><?= $idx % 3 == 0 ? '🧘' : ($idx % 3 == 1 ? '🙏' : '💫') ?></span>
                    <span class="text-xs uppercase font-bold tracking-wider" style="color:var(--brand-purple);font-family:'Montserrat',sans-serif;"><?= htmlspecialchars($post['category']) ?></span>
                  </div>
                </div>
              <?php endif; ?>

              <!-- Content -->
              <div class="p-6">
                <div class="flex items-center gap-3 text-xs text-gray-400 mb-3">
                  <span>⏱️ <?= $post['reading_time'] ?> min read</span>
                  <span>•</span>
                  <span><?= date('M j, Y', strtotime($post['created_at'])) ?></span>
                </div>
                <h2 class="text-xl font-bold mb-3 leading-snug line-clamp-2 hover:text-teal-700 transition">
                  <a href="/blog/<?= htmlspecialchars($post['slug']) ?>" class="no-underline text-inherit">
                    <?= htmlspecialchars($post['title']) ?>
                  </a>
                </h2>
                <p class="text-sm text-gray-600 line-clamp-3 font-light leading-relaxed mb-4">
                  <?= htmlspecialchars($post['excerpt']) ?>
                </p>
              </div>
            </div>

            <div class="p-6 pt-0 border-t border-gray-50 flex items-center justify-between">
              <span class="text-xs font-medium text-gray-400">By Dr. V</span>
              <a href="/blog/<?= htmlspecialchars($post['slug']) ?>" class="text-sm font-semibold flex items-center gap-1 hover:underline" style="color:var(--brand-teal);">
                Read Article →
              </a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </main>

  <!-- Banner to App -->
  <section class="max-w-6xl mx-auto px-6 mb-16 w-full">
    <div class="rounded-3xl p-8 sm:p-12 text-white flex flex-col md:flex-row items-center justify-between gap-8 shadow-xl" style="background:linear-gradient(135deg, var(--brand-purple), #4e4ea2, var(--brand-teal));">
      <div>
        <span class="text-xs font-bold uppercase tracking-wider block mb-2 text-teal-200">Start Your Healing Routine</span>
        <h3 class="text-2xl sm:text-3xl font-extrabold text-white mb-3">Practice the Three Pillars with Dr. V</h3>
        <p class="text-sm sm:text-base text-purple-100 max-w-xl font-light">
          Get guided breathing timers, daily meditations, and gratitude logs right on your smartphone. Free to download on iOS & Android.
        </p>
      </div>
      <div class="flex flex-wrap gap-4 flex-shrink-0">
        <a href="https://apps.apple.com/ca/app/dr-v-healing/id6757068416" target="_blank" rel="noopener" class="bg-white text-gray-900 px-6 py-3 rounded-xl font-bold text-sm shadow hover:bg-gray-100 transition flex items-center gap-2">
          🍎 App Store
        </a>
        <a href="https://play.google.com/store/apps/details?id=com.drvhealing.app" target="_blank" rel="noopener" class="bg-white text-gray-900 px-6 py-3 rounded-xl font-bold text-sm shadow hover:bg-gray-100 transition flex items-center gap-2">
          🤖 Google Play
        </a>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="bg-slate-900 text-gray-400 py-10 px-6 border-t border-slate-800 text-xs text-center">
    <div class="max-w-6xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4">
      <p>© 2026 Dr. V Healing & Meditation. All Rights Reserved.</p>
      <div class="flex gap-6">
        <a href="/" class="hover:text-white">Home</a>
        <a href="/#pillars" class="hover:text-white">Three Pillars</a>
        <a href="/#books" class="hover:text-white">Books</a>
        <a href="/#quiz" class="hover:text-white">Quiz</a>
        <a href="/blog" class="hover:text-white">Blog</a>
      </div>
    </div>
  </footer>

</body>
</html>
