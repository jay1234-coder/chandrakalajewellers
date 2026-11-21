<?php
date_default_timezone_set('Asia/Kolkata');

// Points directly to your operational PHP generation endpoints
$qr_top_url    = "generate_qr.php";
$qr_bottom_url = "generate_qr_1.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Dual Payment QR Print Engine - Chandrakala Jewellers</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<style>
    /* Premium Dashboard UI Theme styling */
    body { background-color: #f1f5f9; font-family: 'Segoe UI', Arial, sans-serif; margin: 0; padding: 0; color: #1e293b; }
    .app-container { max-width: 1000px; margin: 40px auto; padding: 20px; }
    .control-card { background: #ffffff; border-radius: 12px; box-shadow: 0 4px 20px rgba(15, 23, 42, 0.08); padding: 30px; border: 1px solid #e2e8f0; text-align: center; position: relative; }
    .terminal-icon { font-size: 48px; color: #960018; margin-bottom: 15px; }
    h2 { margin: 0 0 10px 0; font-size: 24px; color: #0f172a; }
    p { color: #64748b; font-size: 15px; margin: 0 0 25px 0; }
    
    /* Modern Dashboard Navigation UI Elements */
    .card-actions-header { display: flex; align-items: center; justify-content: center; gap: 15px; margin-bottom: 5px; }
    .btn-back-dashboard { display: inline-flex; align-items: center; gap: 8px; background: #ffffff; color: #475569; border: 1px solid #cbd5e1; padding: 14px 22px; border-radius: 8px; font-weight: 600; font-size: 16px; text-decoration: none; cursor: pointer; transition: all 0.2s ease; }
    .btn-back-dashboard:hover { background: #f8fafc; color: #0f172a; border-color: #94a3b8; }
    
    .btn-action { background: #960018; color: #ffffff; border: none; padding: 14px 28px; border-radius: 8px; font-weight: 600; font-size: 16px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: background 0.2s ease; }
    .btn-action:hover { background: #730012; }
    
    /* Elegant Modal Popup UI Styles */
    .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 9999; opacity: 0; pointer-events: none; transition: opacity 0.3s ease; }
    .modal-overlay.active { opacity: 1; pointer-events: auto; }
    
    .modal-card { background: #ffffff; width: 480px; border-radius: 16px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); overflow: hidden; transform: scale(0.9); transition: transform 0.3s ease; border: 1px solid #d4af37; }
    .modal-overlay.active .modal-card { transform: scale(1); }
    
    .modal-header { background: #960018; color: #ffffff; padding: 20px; font-size: 18px; font-weight: 600; text-align: left; display: flex; align-items: center; gap: 10px; border-bottom: 3px solid #d4af37; }
    .modal-body { padding: 30px 25px; text-align: center; }
    
    .popup-question { font-size: 16px; color: #1e293b; font-weight: 600; line-height: 1.5; margin: 0; }
    
    .modal-footer { padding: 15px 25px; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: center; gap: 16px; }
    .btn-secondary { background: #e2e8f0; color: #475569; border: none; padding: 10px 24px; border-radius: 6px; font-weight: 600; font-size: 14px; cursor: pointer; }
    .btn-secondary:hover { background: #cbd5e1; }
    
    /* Live Preview Responsive Scaling Container */
    .preview-canvas-wrapper { display: none; margin-top: 30px; background: #ffffff; padding: 25px; border-radius: 8px; border: 1px dashed #cbd5e1; text-align: left; }
    
    .qr-frame-container { 
        position: relative; 
        width: 100%; 
        height: 280px; 
        margin-bottom: 20px; 
        overflow: hidden; 
        border: 1px solid #cbd5e1; 
        border-radius: 6px; 
        background: #fff;
    }
    
    .qr-frame-view { 
        position: absolute;
        top: 0;
        left: 0;
        width: 1920px; 
        height: 1080px; 
        border: none;
        transform: scale(0.25); 
        transform-origin: top left;
    }

    /* Print View Setup */
    @media print {
        body { background: #ffffff; }
        .app-container, .modal-overlay { display: none !important; }
    }
</style>
</head>
<body>

<div class="app-container">
    <div class="control-card">
        <div class="terminal-icon"><i class="fas fa-print"></i></div>
        <h2>Dual Payment Terminal Alignment Suite</h2>
        <p>Prepare and print both standard business layout merchant payment terminals seamlessly stacked onto the invoice reverse face.</p>
        
        <div class="card-actions-header">
            <a href="dashboard.php" class="btn-back-dashboard">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <button class="btn-action" onclick="openPrintWizard()">
                <i class="fas fa-sliders-h"></i> Initialize Print Configuration
            </button>
        </div>
        
        <div class="preview-canvas-wrapper" id="previewCanvas">
            <h4 style="margin: 0 0 20px 0; color:#64748b; text-transform: uppercase; font-size:12px; letter-spacing: 0.5px;">Live Terminal Verification Preview</h4>
            
            <div class="qr-frame-container">
                <iframe id="frameTop" src="<?= $qr_top_url; ?>" class="qr-frame-view" scrolling="no"></iframe>
            </div>
            
            <div class="qr-frame-container" style="margin-bottom: 0;">
                <iframe id="frameBottom" src="<?= $qr_bottom_url; ?>" class="qr-frame-view" scrolling="no"></iframe>
            </div>
        </div>
    </div>
</div>

<div class="modal-overlay" id="alignmentModal">
    <div class="modal-card">
        <div class="modal-header">
            <i class="fas fa-question-circle"></i> Alignment Verification
        </div>
        <div class="modal-body">
            <p class="popup-question">Did you flip the same bill and put it inside to print the QR?</p>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closePrintWizard()">No, Cancel</button>
            <button class="btn-action" style="padding: 10px 24px; font-size: 14px;" onclick="executeHardwarePrint()">
                <i class="fas fa-print"></i> Yes, Print
            </button>
        </div>
    </div>
</div>

<script>
    const modalEl   = document.getElementById('alignmentModal');
    const previewEl = document.getElementById('previewCanvas');

    function adjustScale() {
        const containers = document.querySelectorAll('.qr-frame-container');
        if (containers.length === 0) return;
        
        const containerWidth = containers[0].offsetWidth;
        const targetWidth = 1920;
        const scaleFactor = containerWidth / targetWidth;
        const computedHeight = 1080 * scaleFactor;
        
        containers.forEach(container => {
            container.style.height = computedHeight + 'px';
            const frame = container.querySelector('iframe');
            if (frame) {
                frame.style.transform = `scale(${scaleFactor})`;
            }
        });
    }

    function openPrintWizard() {
        previewEl.style.display = 'block';
        adjustScale();
        window.addEventListener('resize', adjustScale);
        modalEl.classList.add('active');
    }

    function closePrintWizard() {
        modalEl.classList.remove('active');
        window.removeEventListener('resize', adjustScale);
    }

    function executeHardwarePrint() {
        closePrintWizard();
        
        const printWindow = window.open('', '_blank');
        if (printWindow) {
            printWindow.document.write(`
                <html>
                <head>
                    <title>Print QR Terminals</title>
                    <style>
                        @page { size: portrait; margin: 0; }
                        body { margin: 0; padding: 0; display: flex; flex-direction: column; align-items: center; justify-content: space-around; height: 100vh; background: #fff; box-sizing: border-box; }
                        img { width: 92%; max-width: 680px; height: auto; object-fit: contain; }
                    </style>
                </head>
                <body>
                    <img src="${document.getElementById('frameTop').src}" />
                    <img src="${document.getElementById('frameBottom').src}" />
                    <script>
                        window.onload = function() {
                            setTimeout(function() {
                                window.print();
                                window.close();
                            }, 400);
                        };
                    <\/script>
                </body>
                </html>
            `);
            printWindow.document.close();
        } else {
            alert("Popup blocked! Please allow popups for this dashboard to initialize the printing pipeline correctly.");
        }
    }
</script>

</body>
</html>