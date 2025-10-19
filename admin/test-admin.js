import puppeteer from 'puppeteer';

async function testAdmin() {
  const browser = await puppeteer.launch({ 
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  const page = await browser.newPage();

  try {
    // Navigate to admin first
    await page.goto('http://localhost:8000/admin');
    
    // First, let's check the API response
    console.log('=== CHECKING API RESPONSE ===');
    
    // Login to get token
    const loginResponse = await page.evaluate(async () => {
      const response = await fetch('http://localhost:8000/api/admin/login', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          email: 'admin@example.com',
          password: 'password'
        })
      });
      return await response.json();
    });
    
    const token = loginResponse.token;
    console.log('Got token:', token ? 'Yes' : 'No');
    
    // Check categories API
    const categoriesResponse = await page.evaluate(async (token) => {
      const response = await fetch('http://localhost:8000/api/admin/categories', {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      return await response.json();
    }, token);
    
    console.log('\nCategories from API:');
    console.log(JSON.stringify(categoriesResponse.slice(0, 2), null, 2));
    
    // Check a specific news item
    const newsResponse = await page.evaluate(async (token) => {
      const response = await fetch('http://localhost:8000/api/admin/news-items/1', {
        headers: {
          'Authorization': `Bearer ${token}`
        }
      });
      return await response.json();
    }, token);
    
    console.log('\nNews item category data:');
    console.log('Category ID:', newsResponse.category_id);
    console.log('Category object:', JSON.stringify(newsResponse.category, null, 2));
    
    // Now let's check the UI
    console.log('\n=== CHECKING UI RENDERING ===');
    
    // Set up console logging
    page.on('console', msg => {
      if (msg.type() === 'error') {
        console.log('Browser console error:', msg.text());
      }
    });
    
    page.on('pageerror', error => {
      console.log('Page error:', error.message);
    });
    
    // Store token in localStorage
    await page.goto('http://localhost:8000/admin');
    await page.evaluate((token) => {
      localStorage.setItem('admin_token', token);
    }, token);
    
    // Navigate to news edit page
    await page.goto('http://localhost:8000/admin/news/1/edit');
    
    // Wait a bit and check what's on the page
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Check if we're redirected to login
    const currentUrl = page.url();
    console.log('Current URL:', currentUrl);
    
    // Try to get any content on the page
    const pageContent = await page.evaluate(() => {
      return {
        title: document.title,
        hasRoot: !!document.getElementById('root'),
        bodyText: document.body.innerText.substring(0, 200)
      };
    });
    console.log('Page content:', pageContent);
    
    // Try to wait for the selector with a longer timeout
    try {
      await page.waitForSelector('select[name="category_id"]', { timeout: 5000 });
    
    // Get the category dropdown options
    const categoryOptions = await page.evaluate(() => {
      const select = document.querySelector('select[name="category_id"]');
      if (!select) return null;
      
      const options = [];
      for (let i = 0; i < select.options.length; i++) {
        options.push({
          value: select.options[i].value,
          text: select.options[i].textContent.trim()
        });
      }
      return options;
    });
    
    console.log('\nCategory dropdown options in UI:');
    console.log(JSON.stringify(categoryOptions, null, 2));
    
    } catch (e) {
      console.log('Could not find category select:', e.message);
    }
    
    // Check what's selected
    const selectedCategory = await page.evaluate(() => {
      const select = document.querySelector('select[name="category_id"]');
      return select ? select.value : null;
    });
    
    console.log('\nCurrently selected category ID:', selectedCategory);
    
  } catch (error) {
    console.error('Error:', error);
  } finally {
    await browser.close();
  }
}

testAdmin();