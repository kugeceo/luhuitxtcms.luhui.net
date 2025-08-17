<?php
// 开启错误显示（开发环境用，生产环境应关闭）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 配置参数
$categories = ['category1', 'category2', 'category3', 'category4', 'category5', 'category6', 'category7'];
$categoryNames = [
    'category1' => '诗词歌赋',
    'category2' => '历史典故',
    'category3' => '科技前沿',
    'category4' => '生活常识',
    'category5' => '艺术鉴赏',
    'category6' => '健康养生',
    'category7' => '体育赛事'
];
$itemsPerPage = 10;
$categoryMaxCounts = [
    'category1' => 1584,
    'category2' => 1584,
    'category3' => 64,
    'category4' => 122
];

// 共享关键词库
$keywordsPool = [
    '文学', '历史', '科技', '健康', '生活', '艺术', '体育',
    '诗歌', '典故', '前沿', '常识', '鉴赏', '养生', '赛事',
    '文化', '知识', '科学', '保健', '技巧', '美学', '运动'
];

// 初始化变量防止未定义错误
$currentCategory = null;
$articleId = null;
$currentPage = 1;
$searchTerm = '';

// 安全获取URL参数
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $currentCategory = trim($_GET['category']);
}
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $articleId = trim($_GET['id']);
}
if (isset($_GET['p']) && is_numeric($_GET['p'])) {
    $currentPage = max(1, intval($_GET['p']));
}
if (isset($_GET['q']) && !empty($_GET['q'])) {
    $searchTerm = trim($_GET['q']);
}

// 检查分类是否有效
if ($currentCategory && !in_array($currentCategory, $categories)) {
    $currentCategory = null;
}

// 生成随机关键词
function generateRandomKeywords($count = 5) {
    global $keywordsPool;
    $tempPool = $keywordsPool; // 使用副本避免影响原数组
    shuffle($tempPool);
    return implode(',', array_slice($tempPool, 0, $count));
}

// 检测编码
function detectEncoding($fileContent) {
    // 检查BOM
    if (substr($fileContent, 0, 3) === "\xef\xbb\xbf") {
        return 'UTF-8';
    }
    
    // 简单UTF-8检测
    if (mb_check_encoding($fileContent, 'UTF-8')) {
        return 'UTF-8';
    }
    
    return 'GBK';
}

// 加载文章
function loadArticle($category, $id) {
    // 验证参数
    if (empty($category) || empty($id)) {
        return null;
    }
    
    $filePath = "{$category}/{$id}.txt";
    // 安全检查路径
    if (strpos($filePath, '..') !== false) {
        return null;
    }
    
    if (!file_exists($filePath) || !is_file($filePath) || !is_readable($filePath)) {
        return null;
    }
    
    // 读取文件内容
    $fileContent = @file_get_contents($filePath);
    if ($fileContent === false) {
        return null;
    }
    
    $encoding = detectEncoding($fileContent);
    
    // 转换为UTF-8
    if ($encoding === 'GBK') {
        $fileContent = iconv('GBK', 'UTF-8//IGNORE', $fileContent);
    }
    
    $lines = explode("\n", $fileContent);
    $title = trim($lines[0] ?? "未命名文章");
    $description = trim($lines[1] ?? "");
    
    // 移除标题和描述行
    array_shift($lines);
    array_shift($lines);
    $content = implode("\n", $lines);
    
    // 判断是否为Markdown
    $isMarkdown = strtolower(substr($title, -3)) === '.md' || 
                  strtolower(substr($id, -3)) === '.md';
                  
    if ($isMarkdown) {
        $title = rtrim($title, '.md');
    }
    
    return [
        'id' => $id,
        'title' => $title,
        'description' => $description,
        'content' => $content,
        'category' => $category,
        'isMarkdown' => $isMarkdown
    ];
}

// 加载分类文章列表
function loadArticlesFromCategory($category, $startId, $endId) {
    if (empty($category) || !is_numeric($startId) || !is_numeric($endId) || $startId > $endId) {
        return [];
    }
    
    $articles = [];
    
    for ($id = $startId; $id <= $endId; $id++) {
        $article = loadArticle($category, $id);
        if ($article) {
            $articles[] = $article;
        } else {
            break; // 文件不存在，停止加载
        }
    }
    
    return $articles;
}

// 获取分类统计
function getCategoryStats() {
    global $categories, $categoryMaxCounts;
    $stats = [];
    
    foreach ($categories as $category) {
        $stats[$category] = isset($categoryMaxCounts[$category]) ? $categoryMaxCounts[$category] : 0;
    }
    
    return $stats;
}

// 搜索过滤
function filterArticlesBySearch($articles, $searchTerm) {
    if (empty($searchTerm) || !is_array($articles)) {
        return $articles;
    }
    
    $filtered = [];
    $searchTerm = strtolower($searchTerm);
    
    foreach ($articles as $article) {
        if (isset($article['title']) && strpos(strtolower($article['title']), $searchTerm) !== false) {
            $filtered[] = $article;
        }
    }
    
    return $filtered;
}

// 分页处理
function paginateArticles($articles, $page, $itemsPerPage) {
    if (!is_array($articles)) {
        $articles = [];
    }
    
    $total = count($articles);
    $totalPages = max(1, ceil($total / $itemsPerPage));
    $start = ($page - 1) * $itemsPerPage;
    
    return [
        'articles' => array_slice($articles, $start, $itemsPerPage),
        'total' => $total,
        'totalPages' => $totalPages
    ];
}

// Markdown解析（简化版）
function parseMarkdown($content) {
    if (empty($content)) {
        return '';
    }
    
    // 标题
    $content = preg_replace('/^# (.*)$/m', '<h1>$1</h1>', $content);
    $content = preg_replace('/^## (.*)$/m', '<h2>$1</h2>', $content);
    $content = preg_replace('/^### (.*)$/m', '<h3>$1</h3>', $content);
    
    // 粗体和斜体
    $content = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $content);
    $content = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $content);
    
    // 列表
    $content = preg_replace('/^- (.*)$/m', '<li>$1</li>', $content);
    $content = preg_replace('/(<li>.*?<\/li>)\s+/s', '<ul>$1</ul>', $content);
    
    // 段落
    $content = preg_replace('/^(?!<h|<ul|<li|<p).*$/m', '<p>$0</p>', $content);
    
    return $content;
}

// 生成URL
function generateUrl($params = []) {
    if (!is_array($params)) {
        $params = [];
    }
    
    global $currentCategory, $currentPage, $searchTerm;
    
    $defaultParams = [];
    if ($currentCategory) $defaultParams['category'] = $currentCategory;
    if ($currentPage) $defaultParams['p'] = $currentPage;
    if ($searchTerm) $defaultParams['q'] = $searchTerm;
    
    $params = array_merge($defaultParams, $params);
    
    // 移除空值
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
        }
    }
    
    return 'index.php' . (empty($params) ? '' : '?' . http_build_query($params));
}

// 获取分类文章
function getCategoryArticles($category, $page = 1) {
    if (empty($category) || !is_numeric($page)) {
        return ['articles' => [], 'total' => 0, 'totalPages' => 1];
    }
    
    global $itemsPerPage, $categoryMaxCounts;
    
    $maxId = min(20, isset($categoryMaxCounts[$category]) ? $categoryMaxCounts[$category] : 20);
    $articles = loadArticlesFromCategory($category, 1, $maxId);
    $articles = filterArticlesBySearch($articles, $GLOBALS['searchTerm']);
    
    return paginateArticles($articles, $page, $itemsPerPage);
}

// 获取所有文章
function getAllArticles($page = 1) {
    if (!is_numeric($page)) {
        return ['articles' => [], 'total' => 0, 'totalPages' => 1];
    }
    
    global $itemsPerPage, $categories, $categoryMaxCounts;
    
    $allArticles = [];
    foreach ($categories as $category) {
        $maxId = min(10, isset($categoryMaxCounts[$category]) ? $categoryMaxCounts[$category] : 10);
        $articles = loadArticlesFromCategory($category, 1, $maxId);
        $allArticles = array_merge($allArticles, $articles);
    }
    
    $allArticles = filterArticlesBySearch($allArticles, $GLOBALS['searchTerm']);
    
    // 排序
    usort($allArticles, function($a, $b) {
        if ($a['category'] === $b['category']) {
            return $a['id'] - $b['id'];
        }
        return strcmp($a['category'], $b['category']);
    });
    
    return paginateArticles($allArticles, $page, $itemsPerPage);
}

// 页面渲染
$pageTitle = "鲁虺文本内容搜索展示系统";
$pageDescription = "高效检索与展示各类文本内容";
$pageKeywords = generateRandomKeywords();

// 处理文章详情页
$articleDetail = null;
if ($articleId && $currentCategory) {
    $articleDetail = loadArticle($currentCategory, $articleId);
    if ($articleDetail) {
        $pageTitle = $articleDetail['title'];
        $pageDescription = $articleDetail['description'] ?: $pageDescription;
        $pageKeywords = generateRandomKeywords();
    }
}

// 获取文章列表数据
$articleListData = ['articles' => [], 'total' => 0, 'totalPages' => 1];
if (!$articleDetail) {
    if ($currentCategory) {
        $articleListData = getCategoryArticles($currentCategory, $currentPage);
    } else {
        $articleListData = getAllArticles($currentPage);
    }
}

$categoryStats = getCategoryStats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?php echo htmlspecialchars($pageDescription); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($pageKeywords); ?>">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
        }
        pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: 'Consolas', 'Monaco', monospace;
            background-color: #f5f5f5;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        .markdown-content h1, .markdown-content h2, .markdown-content h3 {
            font-weight: bold;
            margin: 1.5rem 0 1rem;
        }
        .markdown-content h1 { font-size: 1.8rem; }
        .markdown-content h2 { font-size: 1.5rem; }
        .markdown-content h3 { font-size: 1.2rem; }
        .markdown-content p { margin-bottom: 1rem; }
        .markdown-content ul, .markdown-content ol { 
            margin-left: 1.5rem; 
            margin-bottom: 1rem;
        }
        .markdown-content ul { list-style-type: disc; }
        .markdown-content ol { list-style-type: decimal; }
        .markdown-content blockquote {
            border-left: 4px solid #ddd;
            padding-left: 1rem;
            margin: 1rem 0;
            color: #666;
        }
        .markdown-content a { color: #3b82f6; text-decoration: underline; }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(59, 130, 246, 0.3);
            border-radius: 50%;
            border-top-color: #3b82f6;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .nav-link.active {
            color: #1e40af;
            font-weight: bold;
            text-decoration: underline;
        }
        .error-message {
            background-color: #fee2e2;
            border: 1px solid #fecaca;
            color: #dc2626;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto p-4 md:p-8 max-w-6xl">
        <header class="mb-8">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800 mb-2">鲁虺文本内容搜索展示系统</h1>
            <p class="text-gray-600">高效检索与展示各类文本内容</p>
        </header>
        
        <div class="mb-6">
            <form method="get" action="index.php" class="flex flex-col md:flex-row gap-3">
                <input type="text" name="q" placeholder="搜索文章标题..."
                    value="<?php echo htmlspecialchars($searchTerm); ?>"
                    class="border border-gray-300 p-2 rounded-md flex-grow focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php if ($currentCategory): ?>
                    <input type="hidden" name="category" value="<?php echo htmlspecialchars($currentCategory); ?>">
                <?php endif; ?>
                <button type="submit" class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600 transition">
                    <i class="fas fa-search mr-1"></i> 搜索
                </button>
            </form>
        </div>
        
        <!-- 分类导航 -->
        <div id="category-nav" class="mb-6 overflow-x-auto pb-2">
            <div class="flex space-x-4 min-w-max">
                <a href="<?php echo generateUrl(['category' => null, 'p' => 1]); ?>" 
                   class="nav-link text-blue-500 hover:text-blue-700 whitespace-nowrap <?php echo !$currentCategory ? 'active' : ''; ?>">
                    全部文章
                </a>
                
                <?php foreach ($categories as $category): ?>
                    <a href="<?php echo generateUrl(['category' => $category, 'p' => 1]); ?>"
                       class="nav-link text-blue-500 hover:text-blue-700 whitespace-nowrap <?php echo $currentCategory === $category ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($categoryNames[$category] ?? $category); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- 分类统计 -->
        <div id="category-stats" class="mb-6 p-4 bg-white rounded-lg shadow-sm">
            <?php 
            $totalCount = array_sum($categoryStats);
            echo '<div class="flex flex-wrap gap-x-6 gap-y-2">';
            echo '<span class="font-medium">文章分类统计：</span>';
            
            foreach ($categoryStats as $category => $count):
                echo "<div><span class='text-gray-700'>{$categoryNames[$category] ?? $category}:</span> <span class='font-medium'>{$count}</span> 篇</div>";
            endforeach;
            
            echo "<div class='w-full mt-2 pt-2 border-t border-gray-100'><span class='font-medium'>总计:</span> {$totalCount} 篇文章</div>";
            echo '</div>';
            ?>
        </div>
        
        <?php if ($articleDetail): ?>
            <!-- 文章详情页 -->
            <div id="article-detail">
                <a href="<?php echo generateUrl(); ?>" class="mb-6 inline-flex items-center bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600 transition">
                    <i class="fas fa-arrow-left mr-2"></i> 返回列表
                </a>
                
                <div class="bg-white p-6 rounded-lg shadow-sm mb-6">
                    <h2 class="text-2xl md:text-3xl font-bold mb-4 text-gray-800"><?php echo htmlspecialchars($articleDetail['title']); ?></h2>
                    <div class="text-gray-500 text-sm mb-6">
                        <span class="mr-4"><i class="fas fa-folder text-gray-400 mr-1"></i> <?php echo htmlspecialchars($categoryNames[$articleDetail['category']] ?? $articleDetail['category']); ?></span>
                        <span><i class="fas fa-file-alt text-gray-400 mr-1"></i> ID: <?php echo htmlspecialchars($articleDetail['id']); ?></span>
                        <?php if (!empty($articleDetail['isMarkdown']) && $articleDetail['isMarkdown']): ?>
                            <span class="ml-4 px-2 py-0.5 bg-blue-100 text-blue-700 text-xs rounded">Markdown</span>
                        <?php endif; ?>
                    </div>
                    <div class="markdown-content text-gray-700 leading-relaxed">
                        <?php 
                        if (!empty($articleDetail['isMarkdown']) && $articleDetail['isMarkdown']) {
                            echo parseMarkdown($articleDetail['content']);
                        } else {
                            echo '<pre>' . htmlspecialchars($articleDetail['content']) . '</pre>';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="flex justify-between">
                    <?php 
                    $prevId = intval($articleId) - 1;
                    $nextId = intval($articleId) + 1;
                    $hasPrev = $prevId >= 1;
                    $hasNext = $nextId <= (isset($categoryMaxCounts[$currentCategory]) ? $categoryMaxCounts[$currentCategory] : 1000);
                    ?>
                    
                    <a href="<?php echo $hasPrev ? generateUrl(['id' => $prevId]) : '#'; ?>" 
                       class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600 transition <?php echo !$hasPrev ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                       <?php echo !$hasPrev ? 'onclick="return false;"' : ''; ?>>
                        <i class="fas fa-chevron-left mr-1"></i> 上一篇
                    </a>
                    
                    <a href="<?php echo $hasNext ? generateUrl(['id' => $nextId]) : '#'; ?>" 
                       class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600 transition <?php echo !$hasNext ? 'opacity-50 cursor-not-allowed' : ''; ?>"
                       <?php echo !$hasNext ? 'onclick="return false;"' : ''; ?>>
                        下一篇 <i class="fas fa-chevron-right ml-1"></i>
                    </a>
                </div>
            </div>
        <?php else: ?>
            <!-- 文章列表容器 -->
            <div id="article-list-container" class="mb-8">
                <div id="article-list" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php if (!empty($articleListData['articles']) && is_array($articleListData['articles'])): ?>
                        <?php foreach ($articleListData['articles'] as $article): ?>
                            <div class="bg-white p-4 rounded-lg shadow-sm hover:shadow-md transition">
                                <a href="<?php echo generateUrl(['id' => $article['id'], 'category' => $article['category']]); ?>" class="block h-full">
                                    <div class="flex justify-between items-start">
                                        <h3 class="font-medium text-gray-800 hover:text-blue-600 line-clamp-2">
                                            <?php echo htmlspecialchars($article['title'] ?? '无标题'); ?>
                                        </h3>
                                        <span class="text-xs px-2 py-1 bg-gray-100 text-gray-600 rounded-full">
                                            <?php echo htmlspecialchars($categoryNames[$article['category']] ?? $article['category']); ?>
                                        </span>
                                    </div>
                                    <p class="text-gray-500 text-sm mt-2">ID: <?php echo htmlspecialchars($article['id'] ?? ''); ?></p>
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-span-full text-center py-10 text-gray-500">
                            <i class="fas fa-search-minus text-3xl mb-2"></i>
                            <p>未找到相关文章</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- 分页控件 -->
                <?php if (!empty($articleListData['totalPages']) && $articleListData['totalPages'] > 1): ?>
                    <div id="pagination" class="flex flex-wrap justify-center gap-2 mt-6">
                        <a href="<?php echo generateUrl(['p' => 1]); ?>" 
                           class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600 transition <?php echo $currentPage == 1 ? 'bg-blue-700 pointer-events-none' : ''; ?>">
                            首页
                        </a>
                        
                        <a href="<?php echo generateUrl(['p' => $currentPage - 1]); ?>" 
                           class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600 transition <?php echo $currentPage == 1 ? 'bg-blue-700 pointer-events-none' : ''; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        
                        <?php 
                        $visiblePages = 5;
                        $startPage = max(1, $currentPage - floor($visiblePages / 2));
                        $endPage = min($startPage + $visiblePages - 1, $articleListData['totalPages']);
                        
                        if ($endPage - $startPage < $visiblePages - 1) {
                            $startPage = max(1, $endPage - $visiblePages + 1);
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <a href="<?php echo generateUrl(['p' => $i]); ?>" 
                               class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600 transition <?php echo $currentPage == $i ? 'bg-blue-700 pointer-events-none' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <a href="<?php echo generateUrl(['p' => $currentPage + 1]); ?>" 
                           class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600 transition <?php echo $currentPage == $articleListData['totalPages'] ? 'bg-blue-700 pointer-events-none' : ''; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        
                        <a href="<?php echo generateUrl(['p' => $articleListData['totalPages']]); ?>" 
                           class="bg-blue-500 text-white p-2 rounded-md hover:bg-blue-600 transition <?php echo $currentPage == $articleListData['totalPages'] ? 'bg-blue-700 pointer-events-none' : ''; ?>">
                            末页
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // 简单的客户端交互增强
        document.addEventListener('DOMContentLoaded', function() {
            // 平滑滚动
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function(e) {
                    if (this.getAttribute('href') === '#') {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>
