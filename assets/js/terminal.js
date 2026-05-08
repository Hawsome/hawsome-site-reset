document.addEventListener( 'DOMContentLoaded', function() {
    const term = document.getElementById("nuke-terminal");
    const prog = document.getElementById("nuke-progress");
    
    if ( !term || !prog ) return;

    function log(msg) { 
        term.innerHTML += "<p style=\"margin:4px 0;\">> " + msg + "</p>"; 
        term.scrollTop = term.scrollHeight; 
    }

    async function step(action_step) {
        let fd = new FormData();
        fd.append("action", "sudo_reset_execute_ajax");
        fd.append("nonce", sudoResetTerminal.nonce);
        fd.append("exec_token", sudoResetTerminal.exec_token);
        fd.append("reset_step", action_step);

        let res = await fetch(sudoResetTerminal.ajaxurl, {method:"POST", body:fd});
        return await res.json();
    }

    async function runNuke() {
        log("STAGE 1: Purging database (custom tables, content, options)...");
        let dbRes = await step("database");
        if(!dbRes.success) { log("CRITICAL ERROR: " + dbRes.data); return; }
        prog.style.width = "20%";
        log("Database completely purged.");

        log("STAGE 2: Initializing filesystem purge (this may take a few minutes)...");
        let fsStatus = "processing";
        let totalDeleted = 0;
        while(fsStatus === "processing") {
            let fsRes = await step("filesystem");
            if(!fsRes.success) { log("CRITICAL ERROR: " + fsRes.data); return; }
            fsStatus = fsRes.data.status;
            totalDeleted += fsRes.data.deleted;
            log("Chunk cleared. Deleted items in this pass: " + fsRes.data.deleted + " (Total: " + totalDeleted + ")");
            prog.style.width = Math.min(90, 20 + (totalDeleted / 50)) + "%"; 
        }
        prog.style.width = "90%";
        log("Filesystem completely purged.");

        log("STAGE 3: Restoring WordPress core defaults (Theme, Admin, Cache)...");
        let rstRes = await step("restore");
        if(!rstRes.success) { log("CRITICAL ERROR: " + rstRes.data); return; }
        prog.style.width = "100%";
        log("System successfully restored.");

        log("WIPE COMPLETE. Redirecting to dashboard...");
        setTimeout(() => { window.location.href = sudoResetTerminal.redirect_url; }, 2000);
    }
    
    setTimeout(runNuke, 1000);
});