/**
 * ChandraKala Jewellers — Follow Us printable QR sheet (landscape PDF)
 * Self-hosted deps: js/vendor/jspdf.umd.min.js, js/vendor/qrcode.min.js
 */
(function (global) {
    'use strict';

    var QR_PX = 512;
    var LOGO_MAX = 120;
    var VENDOR = {
        jspdf: 'js/vendor/jspdf.umd.min.js',
        qrcode: 'js/vendor/qrcode.min.js'
    };

    function scriptBase() {
        var scripts = document.getElementsByTagName('script');
        var i;
        for (i = scripts.length - 1; i >= 0; i--) {
            var src = scripts[i].src || '';
            if (src.indexOf('follow-us-qr-pdf.js') !== -1) {
                return src.replace(/[^/]*$/, '');
            }
        }
        return 'js/';
    }

    function vendorUrl(relativeFromJs) {
        /* Prefer path relative to this file so /follow-us pretty URLs still resolve */
        var base = scriptBase();
        if (relativeFromJs.indexOf('js/') === 0) {
            return base + relativeFromJs.replace(/^js\//, '');
        }
        return relativeFromJs;
    }

    function resolveJsPdfCtor() {
        if (global.jspdf && typeof global.jspdf.jsPDF === 'function') {
            return global.jspdf.jsPDF;
        }
        if (typeof global.jsPDF === 'function') {
            return global.jsPDF;
        }
        return null;
    }

    function loadScriptOnce(src) {
        return new Promise(function (resolve, reject) {
            var existing = document.querySelector('script[data-cj-src="' + src + '"], script[src="' + src + '"]');
            if (existing) {
                if (existing.getAttribute('data-cj-loaded') === '1') {
                    resolve();
                    return;
                }
                if (existing.getAttribute('data-cj-failed') === '1') {
                    reject(new Error('Script previously failed: ' + src));
                    return;
                }
                existing.addEventListener('load', function () { resolve(); });
                existing.addEventListener('error', function () {
                    reject(new Error('Failed to load ' + src));
                });
                return;
            }

            var s = document.createElement('script');
            s.src = src;
            s.async = true;
            s.setAttribute('data-cj-src', src);
            s.onload = function () {
                s.setAttribute('data-cj-loaded', '1');
                resolve();
            };
            s.onerror = function () {
                s.setAttribute('data-cj-failed', '1');
                reject(new Error('Failed to load ' + src));
            };
            document.head.appendChild(s);
        });
    }

    function waitFor(checkFn, label, attempts) {
        attempts = attempts || 40;
        return new Promise(function (resolve, reject) {
            var n = 0;
            (function poll() {
                var value = checkFn();
                if (value) {
                    resolve(value);
                    return;
                }
                n += 1;
                if (n > attempts) {
                    reject(new Error(label + ' not available after load'));
                    return;
                }
                setTimeout(poll, 25);
            })();
        });
    }

    function ensureJsPdf() {
        var existing = resolveJsPdfCtor();
        if (existing) return Promise.resolve(existing);

        var localSrc = vendorUrl('js/vendor/jspdf.umd.min.js');
        return loadScriptOnce(localSrc)
            .then(function () {
                return waitFor(resolveJsPdfCtor, 'jsPDF');
            })
            .catch(function (err) {
                throw new Error('jsPDF library missing (' + (err && err.message ? err.message : 'load failed') + '). Expected ' + localSrc);
            });
    }

    function ensureQrCode() {
        if (typeof QRCode !== 'undefined') return Promise.resolve();

        var localSrc = vendorUrl('js/vendor/qrcode.min.js');
        return loadScriptOnce(localSrc)
            .then(function () {
                return waitFor(function () {
                    return typeof QRCode !== 'undefined' ? true : null;
                }, 'QRCode');
            })
            .catch(function (err) {
                throw new Error('QRCode library missing (' + (err && err.message ? err.message : 'load failed') + '). Expected ' + localSrc);
            });
    }

    function makeQrDataUrl(text, size, level) {
        return new Promise(function (resolve, reject) {
            if (typeof QRCode === 'undefined') {
                reject(new Error('QRCode library missing'));
                return;
            }

            var host = document.createElement('div');
            host.setAttribute('aria-hidden', 'true');
            host.style.cssText = 'position:fixed;left:-9999px;top:0;width:' + size + 'px;height:' + size + 'px;overflow:hidden;';
            document.body.appendChild(host);

            var correctLevel = QRCode.CorrectLevel.M;
            if (level === 'H' && QRCode.CorrectLevel.H != null) correctLevel = QRCode.CorrectLevel.H;
            if (level === 'L' && QRCode.CorrectLevel.L != null) correctLevel = QRCode.CorrectLevel.L;

            try {
                new QRCode(host, {
                    text: String(text || ''),
                    width: size,
                    height: size,
                    colorDark: '#000000',
                    colorLight: '#ffffff',
                    correctLevel: correctLevel
                });
            } catch (err) {
                if (host.parentNode) document.body.removeChild(host);
                reject(err);
                return;
            }

            var tries = 0;
            (function poll() {
                tries += 1;
                var canvas = host.querySelector('canvas');
                var img = host.querySelector('img');
                var dataUrl = null;

                if (canvas && canvas.width) {
                    try {
                        dataUrl = canvas.toDataURL('image/png');
                    } catch (e) { /* continue */ }
                }
                if (!dataUrl && img && img.src && img.src.indexOf('data:image') === 0) {
                    dataUrl = img.src;
                }

                if (dataUrl) {
                    if (host.parentNode) document.body.removeChild(host);
                    resolve(dataUrl);
                    return;
                }
                if (tries > 60) {
                    if (host.parentNode) document.body.removeChild(host);
                    reject(new Error('QR render timeout for ' + String(text).slice(0, 48)));
                    return;
                }
                setTimeout(poll, 25);
            })();
        });
    }

    function makeQrDataUrlResilient(text, size) {
        /* H is nicer for print; fall back if payload is too long for that level */
        return makeQrDataUrl(text, size, 'H').catch(function () {
            return makeQrDataUrl(text, size, 'M');
        }).catch(function () {
            return makeQrDataUrl(text, size, 'L');
        });
    }

    function loadImageDataUrl(src, maxSide) {
        return new Promise(function (resolve) {
            if (!src) {
                resolve(null);
                return;
            }
            var img = new Image();
            img.decoding = 'async';
            img.onload = function () {
                try {
                    var w = img.naturalWidth || img.width;
                    var h = img.naturalHeight || img.height;
                    if (!w || !h) {
                        resolve(null);
                        return;
                    }
                    var scale = Math.min(1, maxSide / Math.max(w, h));
                    var cw = Math.max(1, Math.round(w * scale));
                    var ch = Math.max(1, Math.round(h * scale));
                    var canvas = document.createElement('canvas');
                    canvas.width = cw;
                    canvas.height = ch;
                    var ctx = canvas.getContext('2d');
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, cw, ch);
                    ctx.drawImage(img, 0, 0, cw, ch);
                    resolve(canvas.toDataURL('image/png'));
                } catch (e) {
                    /* CORS / decode issues must not block PDF */
                    resolve(null);
                }
            };
            img.onerror = function () { resolve(null); };
            try {
                img.src = src;
            } catch (e) {
                resolve(null);
            }
        });
    }

    function setButtonsBusy(busy) {
        var buttons = document.querySelectorAll('[data-qr-pdf]');
        buttons.forEach(function (btn) {
            btn.disabled = !!busy;
            if (busy) {
                btn.setAttribute('aria-busy', 'true');
                if (!btn.dataset.label) btn.dataset.label = btn.textContent.trim();
                btn.textContent = 'Preparing PDF…';
            } else {
                btn.removeAttribute('aria-busy');
                if (btn.dataset.label) btn.textContent = btn.dataset.label;
            }
        });
    }

    function drawSymbol(doc, kind, x, y, size, color) {
        var s = size;
        var cx = x + s / 2;
        var cy = y + s / 2;
        doc.setDrawColor(color[0], color[1], color[2]);
        doc.setFillColor(color[0], color[1], color[2]);
        doc.setTextColor(color[0], color[1], color[2]);
        doc.setLineWidth(0.4);

        if (kind === 'web') {
            doc.circle(cx, cy, s / 2 - 0.35, 'S');
            doc.line(x + 0.7, cy, x + s - 0.7, cy);
            if (typeof doc.ellipse === 'function') {
                doc.ellipse(cx, cy, s * 0.22, s / 2 - 0.35, 'S');
            } else {
                doc.circle(cx, cy, s * 0.22, 'S');
            }
        } else if (kind === 'ig') {
            var r = 1.15;
            if (typeof doc.roundedRect === 'function') {
                doc.roundedRect(x + 0.35, y + 0.35, s - 0.7, s - 0.7, r, r, 'S');
            } else {
                doc.rect(x + 0.35, y + 0.35, s - 0.7, s - 0.7, 'S');
            }
            doc.circle(cx, cy, s * 0.22, 'S');
            doc.circle(x + s * 0.72, y + s * 0.28, 0.55, 'F');
        } else {
            doc.circle(cx, cy, s / 2 - 0.3, 'S');
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(Math.max(7, s * 0.95));
            doc.text('G', cx, cy + s * 0.22, { align: 'center' });
        }
    }

    function buildPdf(jsPDF, cfg, qrUrls, logoDataUrl) {
        var colorMode = cfg.mode !== 'bw';
        var doc = new jsPDF({
            orientation: 'landscape',
            unit: 'mm',
            format: 'a4',
            compress: true
        });

        var pageW = 297;
        var pageH = 210;
        var margin = 12;

        var gold = [184, 151, 44];
        var goldDark = [146, 118, 28];
        var ink = colorMode ? [28, 25, 23] : [0, 0, 0];
        var muted = colorMode ? [90, 80, 60] : [55, 55, 55];
        var line = colorMode ? gold : [0, 0, 0];
        var cardBorder = colorMode ? [212, 175, 55] : [0, 0, 0];
        var cream = [255, 250, 240];
        var paper = colorMode ? cream : [255, 255, 255];

        doc.setFillColor(paper[0], paper[1], paper[2]);
        doc.rect(0, 0, pageW, pageH, 'F');

        if (colorMode) {
            doc.setFillColor(gold[0], gold[1], gold[2]);
            doc.rect(0, 0, pageW, 3.2, 'F');
            doc.setFillColor(goldDark[0], goldDark[1], goldDark[2]);
            doc.rect(0, pageH - 2.4, pageW, 2.4, 'F');
        } else {
            doc.setFillColor(0, 0, 0);
            doc.rect(0, 0, pageW, 1.6, 'F');
            doc.rect(0, pageH - 1.6, pageW, 1.6, 'F');
        }

        var headerY = 14;
        var logoSize = 16;
        var titleX = margin;

        if (logoDataUrl) {
            try {
                doc.addImage(logoDataUrl, 'PNG', margin, headerY - 2, logoSize, logoSize);
                titleX = margin + logoSize + 5;
            } catch (e) {
                titleX = margin;
            }
        }

        doc.setTextColor(ink[0], ink[1], ink[2]);
        doc.setFont('times', 'bold');
        doc.setFontSize(22);
        doc.text(cfg.brandName, titleX, headerY + 5);

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(10);
        doc.setTextColor(muted[0], muted[1], muted[2]);
        doc.text(cfg.tagline, titleX, headerY + 11.5);

        doc.setDrawColor(line[0], line[1], line[2]);
        doc.setLineWidth(colorMode ? 0.55 : 0.7);
        doc.line(margin, headerY + 16.5, pageW - margin, headerY + 16.5);

        doc.setFont('helvetica', 'bold');
        doc.setFontSize(9);
        doc.setTextColor(ink[0], ink[1], ink[2]);
        doc.text('Scan to connect  ·  Print & stick in-store', pageW / 2, headerY + 22, { align: 'center' });

        var items = [
            {
                key: 'website',
                label: 'Website',
                number: '1',
                symbol: 'web',
                caption: cfg.siteUrl.replace(/^https?:\/\//, ''),
                qr: qrUrls.website
            },
            {
                key: 'instagram',
                label: 'Instagram',
                number: '2',
                symbol: 'ig',
                caption: cfg.instagramHandle,
                qr: qrUrls.instagram
            },
            {
                key: 'google',
                label: 'Google Reviews',
                number: '3',
                symbol: 'star',
                caption: 'Leave a review',
                qr: qrUrls.google
            }
        ];

        var top = 42;
        var gap = 8;
        var usable = pageW - margin * 2;
        var colW = (usable - gap * 2) / 3;
        var qrPad = 5;
        var qrSize = Math.min(62, colW - qrPad * 2);
        var cardH = 118;

        items.forEach(function (item, idx) {
            var x = margin + idx * (colW + gap);
            var y = top;

            doc.setDrawColor(cardBorder[0], cardBorder[1], cardBorder[2]);
            doc.setLineWidth(colorMode ? 0.45 : 0.85);
            doc.setFillColor(255, 255, 255);
            if (typeof doc.roundedRect === 'function') {
                doc.roundedRect(x, y, colW, cardH, colorMode ? 3 : 2, colorMode ? 3 : 2, 'FD');
            } else {
                doc.rect(x, y, colW, cardH, 'FD');
            }

            var badgeR = 4.2;
            var bx = x + 8;
            var by = y + 9;
            if (colorMode) {
                doc.setFillColor(gold[0], gold[1], gold[2]);
            } else {
                doc.setFillColor(0, 0, 0);
            }
            doc.circle(bx, by, badgeR, 'F');
            doc.setTextColor(255, 255, 255);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(11);
            doc.text(item.number, bx, by + 1.1, { align: 'center' });

            var symSize = 7.5;
            drawSymbol(doc, item.symbol, x + colW - symSize - 6, y + 5.2, symSize, colorMode ? goldDark : [0, 0, 0]);

            doc.setTextColor(ink[0], ink[1], ink[2]);
            doc.setFont('times', 'bold');
            doc.setFontSize(13);
            doc.text(item.label, x + colW / 2, y + 11, { align: 'center' });

            var qrX = x + (colW - qrSize) / 2;
            var qrY = y + 18;
            doc.setFillColor(255, 255, 255);
            doc.setDrawColor(colorMode ? 210 : 0, colorMode ? 210 : 0, colorMode ? 210 : 0);
            doc.setLineWidth(0.35);
            doc.rect(qrX - 2, qrY - 2, qrSize + 4, qrSize + 4, 'FD');
            doc.addImage(item.qr, 'PNG', qrX, qrY, qrSize, qrSize);

            doc.setFont('helvetica', 'bold');
            doc.setFontSize(10);
            doc.setTextColor(ink[0], ink[1], ink[2]);
            doc.text(item.label.toUpperCase(), x + colW / 2, qrY + qrSize + 8, { align: 'center' });

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(8);
            doc.setTextColor(muted[0], muted[1], muted[2]);
            doc.text(item.caption, x + colW / 2, qrY + qrSize + 13.5, {
                align: 'center',
                maxWidth: colW - 8
            });
        });

        var footY = pageH - 14;
        doc.setDrawColor(line[0], line[1], line[2]);
        doc.setLineWidth(0.35);
        doc.line(margin, footY - 6, pageW - margin, footY - 6);

        doc.setFont('helvetica', 'normal');
        doc.setFontSize(8.5);
        doc.setTextColor(muted[0], muted[1], muted[2]);
        var addr = cfg.addressLine || '';
        var phone = cfg.phoneDisplay || '';
        var foot = [addr, phone, cfg.siteUrl.replace(/^https?:\/\//, '')].filter(Boolean).join('   ·   ');
        doc.text(foot, pageW / 2, footY - 1.5, { align: 'center' });

        doc.setFontSize(7.5);
        doc.text(
            colorMode ? 'Colour print sheet  ·  ChandraKala Jewellers' : 'Black & white print sheet  ·  ChandraKala Jewellers',
            pageW / 2,
            footY + 3.5,
            { align: 'center' }
        );

        var suffix = colorMode ? 'color' : 'bw';
        doc.save('ChandraKala-Jewellers-QR-codes-' + suffix + '.pdf');
    }

    function errMessage(err) {
        if (!err) return 'Unknown error';
        if (typeof err === 'string') return err;
        if (err.message) return err.message;
        return String(err);
    }

    function downloadQrPdf(mode) {
        var cfg = global.CJ_FOLLOW_QR || {};
        if (!cfg.siteUrl || !cfg.instagramUrl || !cfg.googleReviewUrl) {
            window.alert('QR download is not configured on this page.');
            return;
        }

        setButtonsBusy(true);

        ensureQrCode()
            .then(function () { return ensureJsPdf(); })
            .then(function (jsPDF) {
                return Promise.all([
                    makeQrDataUrlResilient(cfg.siteUrl, QR_PX),
                    makeQrDataUrlResilient(cfg.instagramUrl, QR_PX),
                    makeQrDataUrlResilient(cfg.googleReviewUrl, QR_PX),
                    loadImageDataUrl(cfg.logoUrl || 'images/logo.png', LOGO_MAX).catch(function () { return null; }),
                    Promise.resolve(jsPDF)
                ]);
            })
            .then(function (results) {
                buildPdf(results[4], {
                    mode: mode,
                    brandName: cfg.brandName || 'ChandraKala Jewellers',
                    tagline: cfg.tagline || 'Gold & silver jewellery · Khedbrahma',
                    siteUrl: cfg.siteUrl,
                    instagramHandle: cfg.instagramHandle || '@chandra.kalajewellers',
                    addressLine: cfg.addressLine || '',
                    phoneDisplay: cfg.phoneDisplay || ''
                }, {
                    website: results[0],
                    instagram: results[1],
                    google: results[2]
                }, results[3] || null);
                setButtonsBusy(false);
            })
            .catch(function (err) {
                console.error('CJ QR PDF error:', err);
                window.alert('Could not create the PDF.\n\n' + errMessage(err));
                setButtonsBusy(false);
            });
    }

    function bind() {
        document.querySelectorAll('[data-qr-pdf]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var mode = btn.getAttribute('data-qr-pdf') || 'color';
                downloadQrPdf(mode);
            });
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }

    global.CJ_downloadFollowQrPdf = downloadQrPdf;
})(window);
