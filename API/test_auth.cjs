const axios = require('axios');
const { wrapper } = require('axios-cookiejar-support');
const { CookieJar } = require('tough-cookie');

const jar = new CookieJar();
const client = wrapper(axios.create({ jar, withCredentials: true }));

async function test() {
  try {
    console.log("Getting CSRF cookie...");
    await client.get('http://127.0.0.1:8000/sanctum/csrf-cookie');
    
    const xsrfCookie = jar.getCookieStringSync('http://127.0.0.1:8000').split('; ').find(c => c.startsWith('XSRF-TOKEN='));
    const xsrfToken = xsrfCookie ? decodeURIComponent(xsrfCookie.split('=')[1]) : '';

    console.log("Logging in...");
    const loginRes = await client.post('http://127.0.0.1:8000/api/central/login', {
      username: 'admin',
      password: 'password'
    }, {
        headers: {
            'Origin': 'http://localhost:5173',
            'Referer': 'http://localhost:5173/',
            'X-XSRF-TOKEN': xsrfToken
        }
    });
    console.log("Login Status:", loginRes.status, loginRes.data);

    console.log("Fetching dashboard stats...");
    console.log("Cookies:", jar.getCookieStringSync('http://127.0.0.1:8000'));
    const dashRes = await client.get('http://127.0.0.1:8000/api/t/school1/dashboard/stats', {
        headers: {
            'Origin': 'http://localhost:5173',
            'Referer': 'http://localhost:5173/',
            'X-XSRF-TOKEN': xsrfToken
        }
    });
    console.log("Dashboard Status:", dashRes.status);
    console.log('Dashboard Data:', JSON.stringify(dashRes.data, null, 2));
  } catch (error) {
    if (error.response) {
      console.error("Error Status:", error.response.status);
      console.error("Error Data:", error.response.data);
    } else {
      console.error("Error:", error.message);
    }
  }
}

test();
