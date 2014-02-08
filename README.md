The goal of this simple script is try to recover a site using the Google Cache.

Google store a snapshot of (every?) page of your domain, so if you lost your site, you can try to recover.

*Note*: it recovers only HTML pages, no assets (js, css) or images/documents.
*Note 2*: Your site will be stores in sites/yoursite.com as static

### Installation

`git clone https://github.com/kopiro/googlecachecrawler`

### Usage

Usage: `php run.php yoursite.com [initpage] [endpage] [timeout]`

* **initpage**: (int) the page to start from
* **endpage**: (int) the page to end to
* **timeout** (int) seconds to wait


### Pay attention

Google will block you if you set a low timeout, so be careful.