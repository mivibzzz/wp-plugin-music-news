# wp-plugin-music-news
A simple WordPress Plugin that will pull new information for you
# NewsPlug

**NewsPlug** is a lightweight WordPress plugin that fetches and displays current news articles using the [NewsAPI](https://newsapi.org/). It outputs a simple list of headlines, each with a short description, source, and link to the full article.

## 🧰 Features

- Fetches live news using the NewsAPI.
- Displays title, source, and description.
- Fully embeddable using a shortcode.

## 🚀 Installation

1. Download or clone this repository.
2. Place the `NewsPlug.php` file into your WordPress `wp-content/plugins/` directory.
3. Go to your WordPress admin panel and activate **NewsPlug** under Plugins.
4. Use the shortcode `[newsplug]` to display the latest news anywhere in your content.

## ✏️ Customization

You can easily change:

### 🔑 1. API Key
Replace the placeholder API key in the plugin code with your own from [NewsAPI](https://newsapi.org/):

```php
$apiKey = 'your_api_key_here';
🌍 2. Country or Topic
Modify the API endpoint URL to fetch news from a specific country or category:

php
Copy
Edit
$url = "https://newsapi.org/v2/top-headlines?country=us&apiKey=$apiKey";
// Example: change `country=us` to `country=gb` or add `&category=technology`
🔢 3. Number of Articles
Limit the number of articles by changing the pageSize parameter in the URL:

php
Copy
Edit
$url = "https://newsapi.org/v2/top-headlines?country=us&pageSize=5&apiKey=$apiKey";
📌 Shortcode
Use this shortcode to embed the news anywhere:

plaintext
Copy
Edit
[music_news_dashboard height="600px"]
📎 Notes
You need a valid API key from NewsAPI.

This plugin does include styling. But you can add your own CSS to style the news output as needed.

For any more inforamtion feel free to contact us at https://mivibzzz.com/

Any custom solutions to your problems can be handled in a matter of minutes
