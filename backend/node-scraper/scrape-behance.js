const puppeteer = require('puppeteer');
const fs = require('fs');

async function autoScroll(page) {
  await page.evaluate(async () => {
    await new Promise((resolve) => {
      let totalHeight = 0;
      const distance = 300;
      const timer = setInterval(() => {
        window.scrollBy(0, distance);
        totalHeight += distance;

        if (totalHeight >= document.body.scrollHeight) {
          clearInterval(timer);
          resolve();
        }
      }, 200);
    });
  });
}

(async () => {
  const username = process.argv[2];
  const url = username;
//   console.log(`🔍 Loading: ${url}`);

  const browser = await puppeteer.launch({
    headless: false, // turn off headless to visually debug
    defaultViewport: null,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  });

  const page = await browser.newPage();

  await page.setUserAgent(
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Safari/537.36'
  );

  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await new Promise((r) => setTimeout(r, 5000)); // allow hydration
    await autoScroll(page);
    await new Promise((r) => setTimeout(r, 5000)); // wait post-scroll

    // Capture screenshot
    await page.screenshot({ path: 'final-view.png', fullPage: true });

    // Now try to extract project cards
    const projects = await page.evaluate(() => {
      const anchors = document.querySelectorAll('a[href*="/gallery/"]');
      return Array.from(anchors).map(el => ({
        title: el.innerText.trim(),
        url: el.href,
        image: el.querySelector('img')?.src || '',
      }));
    });

    // console.log(JSON.stringify(projects, null, 2));
    process.stdout.write(JSON.stringify(projects));
    process.exit(0);
} catch (err) {
    console.error('❌ Scraping failed:', err.message);
  } finally {
    await browser.close();
  }
})();
