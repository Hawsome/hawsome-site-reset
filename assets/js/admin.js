document.addEventListener( 'DOMContentLoaded', function() {
    var analyzeBtn = document.getElementById( 'site-nuke-run-analysis' );
    var progressArea = document.getElementById( 'site-nuke-analysis-progress' );
    var impactReport = document.getElementById( 'site-nuke-impact-report' );
    var confirmationArea = document.getElementById( 'site-nuke-confirmation' );
    var confirmInput = document.getElementById( 'site-nuke-confirm' );
    var submitButton = document.getElementById( 'site-nuke-submit' );
    var loadingText = document.querySelector( '.site-nuke-loading-text' );
    var progressBar = document.querySelector( '.site-nuke-progress-bar' );

    var statData = {};

    if ( analyzeBtn ) {
        analyzeBtn.addEventListener( 'click', function() {
            analyzeBtn.style.display = 'none';
            progressArea.style.display = 'block';
            progressBar.classList.add('is-active');
            progressBar.style.width = '10%';
            runAnalysisStep( 'init' );
        });
    }

    function runAnalysisStep( step ) {
        var data = new FormData();
        data.append( 'action', 'site_nuke_analyze' );
        data.append( 'nonce', siteNukeData.nonce );
        data.append( 'scan_step', step );

        fetch( siteNukeData.ajaxurl, { method: 'POST', body: data } )
        .then( response => response.json() )
        .then( result => {
            if ( ! result.success ) { alert( 'Analysis failed: ' + result.data ); return; }

            if ( step === 'init' ) {
                statData = result.data; // Store rich arrays
                progressBar.style.width = '40%';
                loadingText.innerText = 'Database scan complete. Initializing filesystem scan...';
                runAnalysisStep( 'scanning_files' );
            } else if ( step === 'scanning_files' ) {
                if ( result.data.status === 'processing' ) {
                    // LIVE UPDATE: Show the user exactly what is happening!
                    progressBar.style.width = '75%';
                    loadingText.innerText = 'Scanning deeply nested files... found ' + result.data.current_files + ' files so far...';
                    runAnalysisStep( 'scanning_files' );
                } else if ( result.data.status === 'complete' ) {
                    progressBar.style.width = '100%';
                    progressBar.classList.remove('is-active');
                    loadingText.innerText = 'Scan Complete!';
                    statData.file_size = result.data.total_size;
                    statData.file_count = result.data.total_files;
                    renderImpactReport();
                }
            }
        }).catch( error => { alert( 'Server connection error.' ); console.error( error ); });
    }

    function buildListHtml( summaryText, items ) {
        if ( ! items || items.length === 0 ) return '';
        var html = '<details class="site-nuke-details"><summary>' + summaryText + '</summary><div class="site-nuke-details-list"><ul>';
        items.forEach( function( item ) { html += '<li>' + item + '</li>'; } );
        html += '</ul></div></details>';
        return html;
    }

    function renderImpactReport() {
        document.getElementById('stat-db-posts').innerText = statData.total_posts + ' Posts & Pages';
        document.getElementById('stat-db-posts-details').innerHTML = buildListHtml('View Breakdown', statData.post_breakdown);

        document.getElementById('stat-db-tables').innerText = statData.table_list.length + ' Custom Tables';
        document.getElementById('stat-db-tables-details').innerHTML = buildListHtml('View Tables', statData.table_list);

        document.getElementById('stat-ext-plugins').innerText = statData.plugin_list.length + ' Plugins, ' + statData.theme_list.length + ' Themes';
        var combinedExt = statData.plugin_list.map(p => p + ' (Plugin)').concat(statData.theme_list.map(t => t + ' (Theme)'));
        document.getElementById('stat-ext-details').innerHTML = buildListHtml('View Extensions', combinedExt);

        document.getElementById('stat-file-size').innerText = statData.file_size + ' (' + statData.file_count + ' Files)';

        setTimeout(function() {
            progressArea.style.display = 'none';
            impactReport.style.display = 'block';
            confirmationArea.style.display = 'block';
        }, 1000);
    }

    if ( confirmInput && submitButton ) {
        var expectedString = confirmInput.getAttribute( 'data-expected' );
        confirmInput.addEventListener( 'input', function() {
            if ( confirmInput.value === expectedString ) submitButton.removeAttribute( 'disabled' );
            else submitButton.setAttribute( 'disabled', 'disabled' );
        });
    }
});