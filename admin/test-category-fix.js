import puppeteer from 'puppeteer';

async function testCategoryFix() {
  const browser = await puppeteer.launch({ 
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  const page = await browser.newPage();

  try {
    console.log('1. Logging in via test page...');
    await page.goto('http://localhost:8000/test-admin.html');
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    console.log('2. Going to admin panel...');
    await page.goto('http://localhost:8000/admin');
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Check if we're authenticated
    const currentUrl = page.url();
    console.log('Current URL:', currentUrl);
    
    if (currentUrl.includes('/login')) {
      console.log('Not authenticated, stopping test');
      await browser.close();
      return;
    }
    
    console.log('3. Going to news list...');
    await page.goto('http://localhost:8000/admin/news');
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Check if categories display correctly in the list
    const categoryInList = await page.evaluate(() => {
      const firstRow = document.querySelector('tbody tr');
      if (!firstRow) return null;
      const categoryCell = firstRow.querySelector('td:nth-child(4)'); // Adjust based on column position
      return categoryCell ? categoryCell.textContent.trim() : null;
    });
    
    console.log('4. Category in news list shows:', categoryInList);
    
    console.log('5. Going to create new news...');
    await page.goto('http://localhost:8000/admin/news/create');
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Check category dropdown
    const categoryOptions = await page.evaluate(() => {
      const select = document.querySelector('select[name="category_id"]');
      if (!select) return null;
      
      const options = [];
      for (let i = 0; i < select.options.length; i++) {
        if (select.options[i].value) { // Skip empty option
          options.push({
            value: select.options[i].value,
            text: select.options[i].textContent.trim()
          });
        }
      }
      return options;
    });
    
    console.log('\n6. Category dropdown options:');
    if (categoryOptions) {
      categoryOptions.forEach(opt => {
        console.log(`   - ID ${opt.value}: "${opt.text}"`);
      });
      
      // Check if any option contains JSON
      const hasJSON = categoryOptions.some(opt => opt.text.includes('{'));
      if (hasJSON) {
        console.log('\n❌ ISSUE: Categories still showing as JSON!');
      } else {
        console.log('\n✅ SUCCESS: Categories displaying correctly!');
      }
    } else {
      console.log('   Could not find category dropdown');
    }
    
  } catch (error) {
    console.error('Error:', error.message);
  } finally {
    await browser.close();
  }
}

testCategoryFix();