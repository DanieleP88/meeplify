<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Meeplify Application</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #fafafa;
        }
        .test-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .test-section h2 {
            margin: 0 0 15px 0;
            color: #37474f;
        }
        .endpoint {
            margin: 10px 0;
            padding: 10px;
            background: #f5f5f5;
            border-radius: 4px;
            font-family: monospace;
        }
        .test-result {
            margin: 5px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .success {
            background: #e8f5e8;
            color: #2e7d2e;
        }
        .error {
            background: #fce8e8;
            color: #d32f2f;
        }
        button {
            background: #2563eb;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
        }
        button:hover {
            background: #1d4ed8;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-ok { background: #4caf50; }
        .status-error { background: #f44336; }
        .status-pending { background: #ff9800; }
    </style>
</head>
<body>
    <h1>🧪 Test Meeplify Application</h1>
    
    <div class="test-section">
        <h2>📋 Application Structure Test</h2>
        <div id="structure-test">
            <p>Verifying that all files are in place...</p>
        </div>
    </div>

    <div class="test-section">
        <h2>🏠 Homepage Test</h2>
        <div id="homepage-test">
            <div class="endpoint">GET /</div>
            <button onclick="testHomepage()">Test Homepage</button>
            <div id="homepage-result"></div>
        </div>
    </div>

    <div class="test-section">
        <h2>🔐 Authentication Test</h2>
        <div id="auth-test">
            <div class="endpoint">GET /api/auth/me</div>
            <button onclick="testAuth()">Test Auth Status</button>
            <div id="auth-result"></div>
        </div>
    </div>

    <div class="test-section">
        <h2>📝 API Endpoints Test</h2>
        <div id="api-test">
            <div class="endpoint">GET /api/templates</div>
            <button onclick="testTemplates()">Test Templates API</button>
            <div id="templates-result"></div>
            
            <div class="endpoint">GET /api/checklists (requires auth)</div>
            <div class="endpoint">POST /api/checklists (requires auth)</div>
            <div class="endpoint">GET /api/sections (requires auth)</div>
            <div class="endpoint">GET /api/items (requires auth)</div>
            <div class="endpoint">GET /api/tags (requires auth)</div>
            <div class="endpoint">GET /api/collaborations (requires auth)</div>
            <div class="endpoint">GET /api/admin (requires admin)</div>
        </div>
    </div>

    <div class="test-section">
        <h2>🎨 Frontend Components Test</h2>
        <div id="frontend-test">
            <button onclick="testFrontend()">Test Frontend Loading</button>
            <div id="frontend-result"></div>
        </div>
    </div>

    <div class="test-section">
        <h2>💾 Database Connection Test</h2>
        <div id="db-test">
            <button onclick="testDatabase()">Test Database</button>
            <div id="db-result"></div>
        </div>
    </div>

    <div class="test-section">
        <h2>📊 Summary</h2>
        <div id="summary">
            <p>Run tests to see results...</p>
        </div>
    </div>

    <script>
        const results = {};

        function displayResult(testName, success, message, containerId) {
            const container = document.getElementById(containerId);
            const resultDiv = document.createElement('div');
            resultDiv.className = `test-result ${success ? 'success' : 'error'}`;
            resultDiv.innerHTML = `
                <span class="status-indicator ${success ? 'status-ok' : 'status-error'}"></span>
                ${message}
            `;
            container.appendChild(resultDiv);
            
            results[testName] = success;
            updateSummary();
        }

        function updateSummary() {
            const total = Object.keys(results).length;
            const passed = Object.values(results).filter(r => r).length;
            const failed = total - passed;
            
            document.getElementById('summary').innerHTML = `
                <p><strong>Test Results:</strong></p>
                <p>✅ Passed: ${passed}</p>
                <p>❌ Failed: ${failed}</p>
                <p>📊 Total: ${total}</p>
                ${total > 0 ? `<p>Success Rate: ${Math.round((passed/total)*100)}%</p>` : ''}
            `;
        }

        function testHomepage() {
            fetch('/')
                .then(response => {
                    if (response.ok) {
                        return response.text();
                    }
                    throw new Error(`HTTP ${response.status}`);
                })
                .then(html => {
                    if (html.includes('Meeplify') && html.includes('app.js')) {
                        displayResult('homepage', true, 'Homepage loads correctly with Meeplify branding and JavaScript', 'homepage-result');
                    } else {
                        displayResult('homepage', false, 'Homepage missing expected content', 'homepage-result');
                    }
                })
                .catch(error => {
                    displayResult('homepage', false, `Homepage failed to load: ${error.message}`, 'homepage-result');
                });
        }

        function testAuth() {
            fetch('/api/auth/me', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success === false && data.errors && data.errors.includes('Not authenticated')) {
                        displayResult('auth', true, 'Auth endpoint correctly returns not authenticated', 'auth-result');
                    } else if (data.success === true && data.data.user_id) {
                        displayResult('auth', true, `User authenticated: ${data.data.name || data.data.email}`, 'auth-result');
                    } else {
                        displayResult('auth', false, 'Unexpected auth response format', 'auth-result');
                    }
                })
                .catch(error => {
                    displayResult('auth', false, `Auth test failed: ${error.message}`, 'auth-result');
                });
        }

        function testTemplates() {
            fetch('/api/templates', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success === false && data.errors && data.errors.includes('Authentication required')) {
                        displayResult('templates', true, 'Templates API correctly requires authentication', 'templates-result');
                    } else if (data.success === true && Array.isArray(data.data)) {
                        displayResult('templates', true, `Templates API returns ${data.data.length} templates`, 'templates-result');
                    } else {
                        displayResult('templates', false, 'Unexpected templates response', 'templates-result');
                    }
                })
                .catch(error => {
                    displayResult('templates', false, `Templates test failed: ${error.message}`, 'templates-result');
                });
        }

        function testFrontend() {
            // Test CSS loading
            const cssTest = document.createElement('link');
            cssTest.rel = 'stylesheet';
            cssTest.href = '/assets/css/main.css';
            cssTest.onload = () => {
                displayResult('css', true, 'CSS file loads successfully', 'frontend-result');
            };
            cssTest.onerror = () => {
                displayResult('css', false, 'CSS file failed to load', 'frontend-result');
            };
            document.head.appendChild(cssTest);

            // Test JavaScript loading
            fetch('/assets/js/app.js')
                .then(response => {
                    if (response.ok) {
                        return response.text();
                    }
                    throw new Error(`HTTP ${response.status}`);
                })
                .then(js => {
                    if (js.includes('MeeplifyApp') && js.includes('class')) {
                        displayResult('js', true, 'JavaScript file loads and contains MeeplifyApp class', 'frontend-result');
                    } else {
                        displayResult('js', false, 'JavaScript file missing expected content', 'frontend-result');
                    }
                })
                .catch(error => {
                    displayResult('js', false, `JavaScript test failed: ${error.message}`, 'frontend-result');
                });
        }

        function testDatabase() {
            // We can't directly test the database from frontend, but we can test if schema file exists
            fetch('/database_schema.sql')
                .then(response => {
                    if (response.ok) {
                        return response.text();
                    }
                    throw new Error(`HTTP ${response.status}`);
                })
                .then(schema => {
                    if (schema.includes('users') && schema.includes('checklists') && schema.includes('google_sub')) {
                        displayResult('schema', true, 'Database schema file exists with correct tables', 'db-result');
                    } else {
                        displayResult('schema', false, 'Database schema missing expected tables', 'db-result');
                    }
                })
                .catch(error => {
                    displayResult('schema', false, `Schema test failed: ${error.message}`, 'db-result');
                });
        }

        // Run structure test on load
        window.onload = function() {
            const files = [
                '/assets/css/main.css',
                '/assets/js/app.js',
                '/app/views/fo_shell.php',
                '/database_schema.sql'
            ];
            
            let checkedFiles = 0;
            let foundFiles = 0;
            
            files.forEach(file => {
                fetch(file, { method: 'HEAD' })
                    .then(response => {
                        if (response.ok) foundFiles++;
                    })
                    .catch(() => {})
                    .finally(() => {
                        checkedFiles++;
                        if (checkedFiles === files.length) {
                            displayResult('structure', foundFiles === files.length, 
                                `Found ${foundFiles}/${files.length} required files`, 'structure-test');
                        }
                    });
            });
        };
    </script>
</body>
</html>