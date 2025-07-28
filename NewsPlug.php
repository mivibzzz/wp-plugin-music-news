<?php
/**
 * Plugin Name: Music News Dashboard
 * Plugin URI: https://yoursite.com/music-news-dashboard
 * Description: A beautiful music news dashboard that aggregates RSS feeds from various music genres including Rap/Hip Hop, R&B, Afrobeats, and Reggae/Dancehall.
 * Version: 1.0.0
 * Author: Your Name
 * License: GPL v2 or later
 * Text Domain: music-news-dashboard
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class MusicNewsDashboard {
    
    private $plugin_url;
    private $plugin_path;
    
    public function __construct() {
        $this->plugin_url = plugin_dir_url(__FILE__);
        $this->plugin_path = plugin_dir_path(__FILE__);
        
        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_get_music_news', array($this, 'ajax_get_music_news'));
        add_action('wp_ajax_nopriv_get_music_news', array($this, 'ajax_get_music_news'));
        add_shortcode('music_news_dashboard', array($this, 'render_dashboard'));
        
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    public function init() {
        // Plugin initialization
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('music-news-dashboard', $this->plugin_url . 'assets/music-news.js', array('jquery'), '1.0.0', true);
        wp_enqueue_style('music-news-dashboard', $this->plugin_url . 'assets/music-news.css', array(), '1.0.0');
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css', array(), '6.0.0');
        
        // Localize script for AJAX
        wp_localize_script('music-news-dashboard', 'musicNewsAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('music_news_nonce')
        ));
    }
    
    public function ajax_get_music_news() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'music_news_nonce')) {
            wp_die('Security check failed');
        }
        
        $genre = sanitize_text_field($_POST['genre']);
        $query = sanitize_text_field($_POST['query']);
        
        $feeds = $this->get_rss_feeds();
        $articles = array();
        
        // Determine which feeds to fetch
        $feeds_to_fetch = ($genre === 'all') ? $feeds : array($genre => $feeds[$genre]);
        
        foreach ($feeds_to_fetch as $genre_key => $urls) {
            foreach ($urls as $feed_data) {
                $feed_articles = $this->fetch_rss_feed($feed_data['url'], $feed_data['name'], $genre_key);
                $articles = array_merge($articles, $feed_articles);
            }
        }
        
        // Filter by search query
        if (!empty($query)) {
            $articles = array_filter($articles, function($article) use ($query) {
                return stripos($article['title'], $query) !== false || 
                       stripos($article['summary'], $query) !== false;
            });
        }
        
        // Sort by date
        usort($articles, function($a, $b) {
            return strtotime($b['published']) - strtotime($a['published']);
        });
        
        // Limit results
        $articles = array_slice($articles, 0, 50);
        
        wp_send_json_success(array(
            'articles' => $articles,
            'total' => count($articles)
        ));
    }
    
    private function get_rss_feeds() {
        return array(
            'rap' => array(
                array('url' => 'https://www.hotnewhiphop.com/rss/news.xml', 'name' => 'HotNewHipHop'),
                array('url' => 'https://www.xxlmag.com/feed/', 'name' => 'XXL Magazine'),
                array('url' => 'https://www.thefader.com/rss/news', 'name' => 'The Fader')
            ),
            'rnb' => array(
                array('url' => 'https://ratedrnb.com/feed/', 'name' => 'Rated R&B'),
                array('url' => 'https://www.soulbounce.com/feed/', 'name' => 'SoulBounce')
            ),
            'afrobeats' => array(
                array('url' => 'https://www.okayafrica.com/rss/', 'name' => 'OkayAfrica'),
                array('url' => 'https://notjustok.com/feed/', 'name' => 'NotJustOk')
            ),
            'reggae' => array(
                array('url' => 'https://www.dancehallmag.com/feed', 'name' => 'Dancehall Mag'),
                array('url' => 'https://unitedreggae.com/rss/', 'name' => 'United Reggae')
            )
        );
    }
    
    private function fetch_rss_feed($url, $source_name, $genre) {
        $articles = array();
        
        // Use WordPress built-in fetch_feed function
        $rss = fetch_feed($url);
        
        if (is_wp_error($rss)) {
            return $articles; // Return empty array on error
        }
        
        $maxitems = $rss->get_item_quantity(10);
        $rss_items = $rss->get_items(0, $maxitems);
        
        foreach ($rss_items as $item) {
            $articles[] = array(
                'title' => $item->get_title(),
                'link' => $item->get_permalink(),
                'summary' => wp_strip_all_tags($item->get_description()),
                'source' => $source_name,
                'genre' => $genre,
                'published' => $item->get_date('Y-m-d H:i:s')
            );
        }
        
        return $articles;
    }
    
    public function render_dashboard($atts) {
        $atts = shortcode_atts(array(
            'height' => '800px'
        ), $atts);
        
        ob_start();
        ?>
        <div id="music-news-dashboard" style="min-height: <?php echo esc_attr($atts['height']); ?>;">
            <div class="mnd-header">
                <div class="mnd-header-content">
                    <h1 class="mnd-logo">🎵 Music News Dashboard</h1>
                    <div class="mnd-controls">
                        <div class="mnd-select-wrapper">
                            <select id="mndGenreSelect">
                                <option value="all">All Genres</option>
                                <option value="rap">🎤 Rap / Hip Hop</option>
                                <option value="rnb">🎵 R&B</option>
                                <option value="afrobeats">🌍 Afrobeats</option>
                                <option value="reggae">🏝️ Reggae / Dancehall</option>
                            </select>
                        </div>
                        <div class="mnd-search-wrapper">
                            <input type="text" id="mndSearchInput" placeholder="Search news...">
                        </div>
                        <button class="mnd-btn" id="mndRefreshBtn">
                            <i class="fas fa-sync-alt"></i>
                            Refresh
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="mnd-main-content">
                <div id="mndStats" class="mnd-stats" style="display: none;">
                    <p id="mndStatsText"></p>
                </div>
                
                <div id="mndLoading" class="mnd-loading">
                    <div><i class="fas fa-spinner"></i></div>
                    <p>Loading latest music news...</p>
                </div>

                <div id="mndErrorContainer"></div>
                <div id="mndNewsContainer"></div>

                <div id="mndNoResults" class="mnd-no-results" style="display: none;">
                    <div><i class="fas fa-search"></i></div>
                    <h3>No articles found</h3>
                    <p>Try adjusting your search terms or selecting a different genre.</p>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Music News Dashboard Settings',
            'Music News Dashboard',
            'manage_options',
            'music-news-dashboard',
            array($this, 'admin_page')
        );
    }
    
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Music News Dashboard</h1>
            <div class="card">
                <h2>How to Use</h2>
                <p>Add the Music News Dashboard to any page or post using the shortcode:</p>
                <code>[music_news_dashboard]</code>
                
                <h3>Shortcode Parameters</h3>
                <ul>
                    <li><strong>height</strong> - Set the minimum height (default: 800px)<br>
                        Example: <code>[music_news_dashboard height="600px"]</code>
                    </li>
                </ul>
                
                <h3>RSS Feeds</h3>
                <p>The plugin fetches news from the following sources:</p>
                <ul>
                    <li><strong>Rap / Hip Hop:</strong> HotNewHipHop, XXL Magazine, The Fader</li>
                    <li><strong>R&B:</strong> Rated R&B, SoulBounce</li>
                    <li><strong>Afrobeats:</strong> OkayAfrica, NotJustOk</li>
                    <li><strong>Reggae / Dancehall:</strong> Dancehall Mag, United Reggae</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    public function activate() {
        // Activation code
        flush_rewrite_rules();
    }
    
    public function deactivate() {
        // Deactivation code
        flush_rewrite_rules();
    }
}

// Initialize the plugin
new MusicNewsDashboard();

// Create assets directory and files on activation
register_activation_hook(__FILE__, 'music_news_create_assets');

function music_news_create_assets() {
    $plugin_dir = plugin_dir_path(__FILE__);
    $assets_dir = $plugin_dir . 'assets/';
    
    // Create assets directory if it doesn't exist
    if (!file_exists($assets_dir)) {
        wp_mkdir_p($assets_dir);
    }
    
    // Create CSS file
    $css_content = '
/* Music News Dashboard Styles */
#music-news-dashboard {
    font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
    color: #f0f0f0;
    min-height: 100vh;
    border-radius: 12px;
    overflow: hidden;
}

.mnd-header {
    background: rgba(0, 0, 0, 0.8);
    backdrop-filter: blur(10px);
    padding: 1rem;
    border-bottom: 1px solid #444;
}

.mnd-header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}

.mnd-logo {
    font-size: 1.8rem;
    font-weight: bold;
    color: #ff6b6b;
    margin: 0;
}

.mnd-controls {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.mnd-select-wrapper select,
.mnd-search-wrapper input {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    padding: 0.75rem 1rem;
    color: #f0f0f0;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    min-width: 150px;
}

.mnd-search-wrapper input::placeholder {
    color: rgba(240, 240, 240, 0.6);
}

.mnd-btn {
    background: linear-gradient(135deg, #ff6b6b, #ee5a52);
    border: none;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    color: white;
    font-size: 0.9rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.mnd-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
}

.mnd-main-content {
    padding: 2rem;
}

.mnd-loading {
    text-align: center;
    padding: 2rem;
    font-size: 1.1rem;
    color: #aaa;
}

.mnd-loading i {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: #ff6b6b;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.mnd-stats {
    text-align: center;
    margin-bottom: 2rem;
    padding: 1rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.mnd-genre-section {
    margin-bottom: 3rem;
}

.mnd-genre-title {
    font-size: 1.5rem;
    font-weight: bold;
    color: #ff6b6b;
    margin-bottom: 1.5rem;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid rgba(255, 107, 107, 0.3);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.mnd-news-grid {
    display: grid;
    gap: 1.5rem;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
}

.mnd-news-card {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.mnd-news-card::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #ff6b6b, #4ecdc4, #45b7d1);
    transform: translateX(-100%);
    transition: transform 0.3s ease;
}

.mnd-news-card:hover::before {
    transform: translateX(0);
}

.mnd-news-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    border-color: rgba(255, 255, 255, 0.2);
}

.mnd-news-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.mnd-news-source {
    background: rgba(255, 107, 107, 0.2);
    color: #ff6b6b;
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 500;
}

.mnd-news-title {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    line-height: 1.4;
}

.mnd-news-title a {
    color: #f0f0f0;
    text-decoration: none;
    transition: color 0.3s ease;
}

.mnd-news-title a:hover {
    color: #ff6b6b;
}

.mnd-news-summary {
    color: rgba(240, 240, 240, 0.8);
    line-height: 1.6;
    font-size: 0.9rem;
    margin-bottom: 1rem;
}

.mnd-news-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
    color: rgba(240, 240, 240, 0.6);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    padding-top: 1rem;
}

.mnd-read-more {
    color: #ff6b6b;
    text-decoration: none;
    font-weight: 500;
    transition: color 0.3s ease;
}

.mnd-read-more:hover {
    color: #ff8a8a;
}

.mnd-no-results {
    text-align: center;
    padding: 3rem;
    color: #aaa;
}

.mnd-no-results i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: #666;
}

.mnd-error-message {
    background: rgba(255, 107, 107, 0.1);
    border: 1px solid rgba(255, 107, 107, 0.3);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    color: #ff6b6b;
}

@media (max-width: 768px) {
    .mnd-header-content {
        flex-direction: column;
        align-items: stretch;
    }
    
    .mnd-controls {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .mnd-news-grid {
        grid-template-columns: 1fr;
    }
}
';
    
    file_put_contents($assets_dir . 'music-news.css', $css_content);
    
    // Create JavaScript file
    $js_content = '
jQuery(document).ready(function($) {
    class MusicNewsDashboard {
        constructor() {
            this.genreSelect = $("#mndGenreSelect");
            this.searchInput = $("#mndSearchInput");
            this.refreshBtn = $("#mndRefreshBtn");
            this.newsContainer = $("#mndNewsContainer");
            this.loading = $("#mndLoading");
            this.stats = $("#mndStats");
            this.statsText = $("#mndStatsText");
            this.noResults = $("#mndNoResults");
            this.errorContainer = $("#mndErrorContainer");
            
            this.initEventListeners();
            this.loadNews();
        }

        initEventListeners() {
            this.genreSelect.on("change", () => this.loadNews());
            this.searchInput.on("input", () => this.debounce(this.loadNews.bind(this), 300)());
            this.refreshBtn.on("click", () => this.loadNews());
        }

        debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        loadNews() {
            this.showLoading();
            this.clearErrors();
            
            this.refreshBtn.html("<i class=\"fas fa-spinner fa-spin\"></i> Loading...");
            this.refreshBtn.prop("disabled", true);
            
            const data = {
                action: "get_music_news",
                genre: this.genreSelect.val(),
                query: this.searchInput.val(),
                nonce: musicNewsAjax.nonce
            };
            
            $.post(musicNewsAjax.ajaxurl, data, (response) => {
                if (response.success) {
                    this.displayNews(response.data.articles);
                    this.updateStats(response.data.total);
                } else {
                    this.showError("Failed to load news articles.");
                }
            }).fail(() => {
                this.showError("Network error occurred while loading news.");
            }).always(() => {
                this.refreshBtn.html("<i class=\"fas fa-sync-alt\"></i> Refresh");
                this.refreshBtn.prop("disabled", false);
                this.hideLoading();
            });
        }

        displayNews(articles) {
            if (articles.length === 0) {
                this.newsContainer.html("");
                this.noResults.show();
                return;
            }

            this.noResults.hide();
            
            // Group articles by genre
            const groupedArticles = {};
            articles.forEach(article => {
                if (!groupedArticles[article.genre]) {
                    groupedArticles[article.genre] = [];
                }
                groupedArticles[article.genre].push(article);
            });
            
            const genreNames = {
                rap: "🎤 Rap / Hip Hop",
                rnb: "🎵 R&B",
                afrobeats: "🌍 Afrobeats",
                reggae: "🏝️ Reggae / Dancehall"
            };
            
            let html = "";
            
            for (const [genre, genreArticles] of Object.entries(groupedArticles)) {
                html += `
                    <div class="mnd-genre-section">
                        <h2 class="mnd-genre-title">
                            ${genreNames[genre] || genre}
                            <span style="font-size: 0.8em; opacity: 0.7;">(${genreArticles.length})</span>
                        </h2>
                        <div class="mnd-news-grid">
                            ${genreArticles.map(article => `
                                <article class="mnd-news-card">
                                    <div class="mnd-news-header">
                                        <span class="mnd-news-source">${this.escapeHtml(article.source)}</span>
                                    </div>
                                    <h3 class="mnd-news-title">
                                        <a href="${article.link}" target="_blank" rel="noopener noreferrer">
                                            ${this.escapeHtml(article.title)}
                                        </a>
                                    </h3>
                                    <p class="mnd-news-summary">${this.truncateText(this.escapeHtml(article.summary), 200)}</p>
                                    <div class="mnd-news-meta">
                                        <span>${this.formatDate(article.published)}</span>
                                        <a href="${article.link}" target="_blank" rel="noopener noreferrer" class="mnd-read-more">
                                            Read More <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    </div>
                                </article>
                            `).join("")}
                        </div>
                    </div>
                `;
            }
            
            this.newsContainer.html(html);
        }

        updateStats(total) {
            const selectedGenre = this.genreSelect.val();
            const searchTerm = this.searchInput.val();
            
            let statsText = `Showing ${total} articles`;
            
            if (selectedGenre !== "all") {
                const genreNames = {
                    rap: "Rap / Hip Hop",
                    rnb: "R&B",
                    afrobeats: "Afrobeats",
                    reggae: "Reggae / Dancehall"
                };
                statsText += ` from ${genreNames[selectedGenre]}`;
            }
            
            if (searchTerm) {
                statsText += ` matching "${searchTerm}"`;
            }
            
            this.statsText.text(statsText);
            this.stats.show();
        }

        showLoading() {
            this.loading.show();
            this.newsContainer.hide();
            this.stats.hide();
            this.noResults.hide();
        }

        hideLoading() {
            this.loading.hide();
            this.newsContainer.show();
        }

        showError(message) {
            this.errorContainer.html(`
                <div class="mnd-error-message">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${message}
                </div>
            `);
        }

        clearErrors() {
            this.errorContainer.html("");
        }

        escapeHtml(text) {
            return $("<div>").text(text).html();
        }

        truncateText(text, maxLength) {
            if (text.length <= maxLength) return text;
            return text.substring(0, maxLength).trim() + "...";
        }

        formatDate(dateString) {
            try {
                const date = new Date(dateString);
                return date.toLocaleDateString("en-US", {
                    month: "short",
                    day: "numeric",
                    year: "numeric"
                });
            } catch (error) {
                return "Recently";
            }
        }
    }

    // Initialize the dashboard if it exists on the page
    if ($("#music-news-dashboard").length) {
        new MusicNewsDashboard();
    }
});
';
    
    file_put_contents($assets_dir . 'music-news.js', $js_content);
}
?>