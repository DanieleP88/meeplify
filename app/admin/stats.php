&lt;?php
$content = &#39;
&lt;h1&gt;Statistics Dashboard&lt;/h1&gt;
&lt;div id=&quot;kpis&quot;&gt;&lt;/div&gt;
&lt;canvas id=&quot;growthChart&quot;&gt;&lt;/canvas&gt;
&lt;button onclick=&quot;exportStats(\&#39;json\&#39;)&quot;&gt;Export JSON&lt;/button&gt;
&lt;button onclick=&quot;exportStats(\&#39;csv\&#39;)&quot;&gt;Export CSV&lt;/button&gt;
&lt;script&gt;
function loadStats() {
    fetch(&quot;/api/admin/stats&quot;, {
        headers: {&quot;X-CSRF-TOKEN&quot;: csrf}
    }).then(res =&gt; res.json()).then(resp =&gt; {
        if (resp.success) {
            const kpisDiv = document.getElementById(&quot;kpis&quot;);
            kpisDiv.innerHTML = `
                &lt;p&gt;Total Users: ${resp.data.kpis.users_total}&lt;/p&gt;
                &lt;p&gt;Total Checklists: ${resp.data.kpis.checklists_total}&lt;/p&gt;
                // add more
            `;
            const ctx = document.getElementById(&quot;growthChart&quot;).getContext(&quot;2d&quot;);
            new Chart(ctx, {
                type: &quot;line&quot;,
                data: {
                    labels: resp.data.growth.map(g =&gt; g.date),
                    datasets: [{
                        label: &quot;User Growth&quot;,
                        data: resp.data.growth.map(g =&gt; g.user_count),
                        borderColor: &#39;blue&#39;
                    }, {
                        label: &quot;Checklist Growth&quot;,
                        data: resp.data.growth.map(g =&gt; g.checklist_count),
                        borderColor: &#39;green&#39;
                    }]
                }
            });
        }
    });
}

function exportStats(format) {
    fetch(&quot;/api/admin/stats&quot;).then(res =&gt; res.json()).then(resp =&gt; {
        if (resp.success) {
            if (format === &quot;json&quot;) {
                const data = JSON.stringify(resp.data);
                const blob = new Blob([data], {type: &quot;application/json&quot;});
                const url = URL.createObjectURL(blob);
                const a = document.createElement(&quot;a&quot;);
                a.href = url;
                a.download = &quot;stats.json&quot;;
                a.click();
            } else if (format === &quot;csv&quot;) {
                let csv = &quot;KPI,Value\\n&quot;;
                for (let key in resp.data.kpis) {
                    csv += `${key},${resp.data.kpis[key]}\\n`;
                }
                csv += &quot;Date,User Count,Checklist Count\\n&quot;;
                resp.data.growth.forEach(g =&gt; {
                    csv += `${g.date},${g.user_count},${g.checklist_count}\\n`;
                });
                const blob = new Blob([csv], {type: &quot;text/csv&quot;});
                const url = URL.createObjectURL(blob);
                const a = document.createElement(&quot;a&quot;);
                a.href = url;
                a.download = &quot;stats.csv&quot;;
                a.click();
            }
        }
    });
}
loadStats();
&lt;/script&gt;
&#39;;
require_once __DIR__ . &#39;/../views/admin_shell.php&#39;;
?&gt;