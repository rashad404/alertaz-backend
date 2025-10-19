import puppeteer from 'puppeteer';

async function testCategoryEdit() {
  const browser = await puppeteer.launch({ 
    headless: true,
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });
  const page = await browser.newPage();

  // Enable console logging
  page.on('console', msg => {
    console.log('Browser console:', msg.text());
  });

  try {
    console.log('1. Logging in...');
    await page.goto('http://localhost:8000/test-admin.html');
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    console.log('2. Going to edit category page...');
    await page.goto('http://localhost:5175/admin/news-categories/1/edit');
    await new Promise(resolve => setTimeout(resolve, 3000));
    
    // Check if we're on the edit page
    const currentUrl = page.url();
    console.log('Current URL:', currentUrl);
    
    // Wait for form to load
    await page.waitForSelector('input[name="title_az"]', { timeout: 5000 }).catch(() => {
      console.log('Could not find title_az input');
    });
    
    // Get all input values
    const formValues = await page.evaluate(() => {
      const values = {};
      const inputs = document.querySelectorAll('input[name], select[name], textarea[name]');
      inputs.forEach(input => {
        if (input instanceof HTMLInputElement || input instanceof HTMLSelectElement || input instanceof HTMLTextAreaElement) {
          values[input.name] = input.value;
        }
      });
      return values;
    });
    
    console.log('\n3. Form field values:');
    console.log('-------------------');
    Object.entries(formValues).forEach(([name, value]) => {
      if (value) {
        console.log(`${name}: "${value}"`);
      } else {
        console.log(`${name}: [EMPTY]`);
      }
    });
    
    // Check if any main fields have values
    const hasValues = formValues.title_az || formValues.title_en || formValues.title_ru || formValues.slug;
    
    if (hasValues) {
      console.log('\n✅ SUCCESS: Form fields are populated with data!');
    } else {
      console.log('\n❌ ISSUE: All form fields are empty!');
      
      // Try to get any error messages
      const errorText = await page.evaluate(() => {
        const errors = document.querySelectorAll('.text-red-600');
        return Array.from(errors).map(e => e.textContent).join(', ');
      });
      
      if (errorText) {
        console.log('Error messages found:', errorText);
      }
    }
    
  } catch (error) {
    console.error('Error:', error.message);
  } finally {
    await browser.close();
  }
}

testCategoryEdit();