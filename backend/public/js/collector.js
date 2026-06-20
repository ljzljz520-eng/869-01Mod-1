// backend/public/js/collector.js - 隐私合规的访客信息采集器

(function () {
    const CONSENT_KEY = 'speedprobe_privacy_consent';
    const VISITOR_KEY = 'speedprobe_visitor_id';

    const getConsentStatus = () => {
        try {
            const status = localStorage.getItem(CONSENT_KEY);
            return status ? parseInt(status, 10) : 0;
        } catch (e) {
            return 0;
        }
    };

    const setConsentStatus = (status) => {
        try {
            localStorage.setItem(CONSENT_KEY, String(status));
        } catch (e) { }
    };

    const getVisitorId = () => {
        try {
            return sessionStorage.getItem(VISITOR_KEY) || localStorage.getItem(VISITOR_KEY);
        } catch (e) {
            return null;
        }
    };

    const setVisitorId = (id) => {
        try {
            sessionStorage.setItem(VISITOR_KEY, id);
            localStorage.setItem(VISITOR_KEY, id);
        } catch (e) { }
    };

    const getPageLoadTime = () => {
        if (window.performance && window.performance.timing) {
            const t = window.performance.timing;
            if (t.loadEventEnd && t.navigationStart) {
                return t.loadEventEnd - t.navigationStart;
            }
        }
        return null;
    };

    const getPageSize = () => {
        try {
            if (window.performance && window.performance.getEntriesByType) {
                const resources = window.performance.getEntriesByType('resource');
                let totalSize = 0;
                resources.forEach(r => {
                    if (r.transferSize) totalSize += r.transferSize;
                });
                return Math.round(totalSize / 1024);
            }
        } catch (e) { }
        return null;
    };

    const parseUserAgent = () => {
        const ua = navigator.userAgent;
        let browser = '未知';
        let browserVersion = '';
        let os = '未知';
        let osVersion = '';
        let deviceType = '桌面设备';

        if (ua.includes('Edg/')) {
            browser = 'Edge';
            browserVersion = ua.match(/Edg\/([\d.]+)/)?.[1] || '';
        } else if (ua.includes('Chrome/') && !ua.includes('Chromium')) {
            browser = 'Chrome';
            browserVersion = ua.match(/Chrome\/([\d.]+)/)?.[1] || '';
        } else if (ua.includes('Firefox/')) {
            browser = 'Firefox';
            browserVersion = ua.match(/Firefox\/([\d.]+)/)?.[1] || '';
        } else if (ua.includes('Safari/') && !ua.includes('Chrome')) {
            browser = 'Safari';
            browserVersion = ua.match(/Version\/([\d.]+)/)?.[1] || '';
        } else if (ua.includes('Opera') || ua.includes('OPR/')) {
            browser = 'Opera';
            browserVersion = ua.match(/(?:Opera|OPR)\/([\d.]+)/)?.[1] || '';
        }

        if (ua.includes('Windows NT 10')) {
            os = 'Windows';
            osVersion = '10/11';
        } else if (ua.includes('Windows NT 6.3')) {
            os = 'Windows';
            osVersion = '8.1';
        } else if (ua.includes('Windows NT 6.1')) {
            os = 'Windows';
            osVersion = '7';
        } else if (ua.includes('Mac OS X')) {
            os = 'macOS';
            osVersion = ua.match(/Mac OS X ([\d_]+)/)?.[1]?.replace(/_/g, '.') || '';
        } else if (ua.includes('iPhone')) {
            os = 'iOS';
            osVersion = ua.match(/iPhone OS ([\d_]+)/)?.[1]?.replace(/_/g, '.') || '';
            deviceType = '手机';
        } else if (ua.includes('iPad')) {
            os = 'iPadOS';
            osVersion = ua.match(/CPU OS ([\d_]+)/)?.[1]?.replace(/_/g, '.') || '';
            deviceType = '平板';
        } else if (ua.includes('Android')) {
            os = 'Android';
            osVersion = ua.match(/Android ([\d.]+)/)?.[1] || '';
            deviceType = ua.includes('Mobile') ? '手机' : '平板';
        } else if (ua.includes('Linux')) {
            os = 'Linux';
            osVersion = '';
        }

        return { browser, browserVersion, os, osVersion, deviceType };
    };

    const getConnectionInfo = () => {
        const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        if (conn) {
            let physicalType = conn.type || '';
            const typeMap = {
                'wifi': 'WiFi',
                'cellular': '蜂窝网络',
                'ethernet': '有线网络',
                'bluetooth': '蓝牙',
                'wimax': 'WiMAX',
                'other': '其他',
                'none': '无网络',
                'unknown': '未知'
            };
            physicalType = typeMap[physicalType] || physicalType;

            const effectiveType = conn.effectiveType || '';
            const speedMap = {
                '4g': '4G (快速)',
                '3g': '3G (中速)',
                '2g': '2G (慢速)',
                'slow-2g': '极慢'
            };
            const speed = speedMap[effectiveType] || effectiveType;

            let result = [];
            if (physicalType) result.push(physicalType);
            if (speed) result.push(speed);
            if (conn.downlink) result.push(`${conn.downlink}Mbps`);

            return {
                type: result.length > 0 ? result.join(' / ') : '未知',
                downlink: conn.downlink ? `${conn.downlink} Mbps` : '',
                rtt: conn.rtt ? `${conn.rtt} ms` : ''
            };
        }
        return { type: '浏览器不支持', downlink: '', rtt: '' };
    };

    const getWebGLInfo = () => {
        try {
            const canvas = document.createElement('canvas');
            const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (gl) {
                const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                if (debugInfo) {
                    return {
                        vendor: gl.getParameter(debugInfo.UNMASKED_VENDOR_WEBGL),
                        renderer: gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL)
                    };
                }
            }
        } catch (e) { }
        return { vendor: '', renderer: '' };
    };

    const getClientHints = async () => {
        const hints = {
            platform: '',
            platformVersion: '',
            mobile: false,
            model: '',
            architecture: ''
        };

        if (navigator.userAgentData) {
            try {
                const highEntropy = await navigator.userAgentData.getHighEntropyValues([
                    'platform',
                    'platformVersion',
                    'architecture',
                    'model',
                    'mobile',
                    'fullVersionList'
                ]);
                hints.platform = highEntropy.platform || '';
                hints.platformVersion = highEntropy.platformVersion || '';
                hints.mobile = highEntropy.mobile || false;
                hints.model = highEntropy.model || '';
                hints.architecture = highEntropy.architecture || '';
            } catch (e) {
                hints.platform = navigator.userAgentData.platform || '';
                hints.mobile = navigator.userAgentData.mobile || false;
            }
        }
        return hints;
    };

    const collectAnonymous = async () => {
        const data = {
            mode: 'anonymous',
            page_load_time: getPageLoadTime(),
            page_size: getPageSize()
        };

        const visitorId = getVisitorId();
        if (visitorId) {
            data.visitor_id = visitorId;
        }

        try {
            const res = await fetch('/api.php?action=collect', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (json.id) {
                setVisitorId(json.id);
            }
            return json;
        } catch (err) {
            console.error('匿名数据采集失败', err);
            return null;
        }
    };

    const collectFull = async () => {
        const uaInfo = parseUserAgent();
        const webglInfo = getWebGLInfo();
        const connInfo = getConnectionInfo();
        const clientHints = await getClientHints();

        let finalOsVersion = uaInfo.osVersion;
        if (clientHints.platformVersion) {
            finalOsVersion = clientHints.platformVersion;
        }

        const data = {
            mode: 'full',
            browser: uaInfo.browser,
            browser_version: uaInfo.browserVersion,
            os: clientHints.platform || uaInfo.os,
            os_version: finalOsVersion,
            device_type: clientHints.mobile ? '手机' : uaInfo.deviceType,

            screen_width: screen.width,
            screen_height: screen.height,
            window_width: window.innerWidth,
            window_height: window.innerHeight,

            language: navigator.language || navigator.userLanguage || '未知',
            platform: navigator.platform || '未知',
            cookie_enabled: navigator.cookieEnabled ? 1 : 0,

            device_memory: navigator.deviceMemory || 0,
            cpu_cores: navigator.hardwareConcurrency || 0,
            touch_points: navigator.maxTouchPoints || 0,
            gpu_vendor: webglInfo.vendor,
            gpu_renderer: webglInfo.renderer,
            architecture: clientHints.architecture || '',

            connection_type: connInfo.type,

            timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,

            referrer: document.referrer || '',

            country: '',
            city: '',
            isp: '',

            page_load_time: getPageLoadTime(),
            page_size: getPageSize()
        };

        const visitorId = getVisitorId();
        if (visitorId) {
            data.visitor_id = visitorId;
        }

        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 3000);

            const ipRes = await fetch('https://ip-api.com/json/?lang=zh-CN', {
                signal: controller.signal
            });
            clearTimeout(timeoutId);

            if (ipRes.ok) {
                const ipData = await ipRes.json();
                if (ipData.status === 'success') {
                    data.country = ipData.country || '';
                    data.city = ipData.city || '';
                    data.isp = ipData.isp || '';
                }
            }
        } catch (e) {
            console.log('IP 定位获取失败');
        }

        try {
            const res = await fetch('/api.php?action=collect', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const json = await res.json();
            if (json.id) {
                setVisitorId(json.id);
            }
            return json;
        } catch (err) {
            console.error('数据采集失败', err);
            return null;
        }
    };

    const grantConsent = async () => {
        const visitorId = getVisitorId();
        if (visitorId) {
            try {
                await fetch('/api.php?action=consent', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: visitorId, grant: true })
                });
            } catch (e) { }
        }
        setConsentStatus(1);
        hideConsentBanner();
        await collectFull();
    };

    const denyConsent = () => {
        setConsentStatus(2);
        hideConsentBanner();
    };

    const withdrawConsent = async () => {
        const visitorId = getVisitorId();
        if (visitorId) {
            try {
                await fetch('/api.php?action=consent', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: visitorId, grant: false })
                });
            } catch (e) { }
        }
        setConsentStatus(2);
        alert('已撤回隐私授权，您的数据将被匿名化处理。');
    };

    const showConsentBanner = () => {
        const banner = document.createElement('div');
        banner.id = 'privacy-consent-banner';
        banner.style.cssText = `
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(10px);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px;
            z-index: 99999;
            color: #e2e8f0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
        `;

        banner.innerHTML = `
            <div style="max-width: 1200px; margin: 0 auto; display: flex; flex-direction: column; gap: 16px; align-items: stretch;">
                <div style="flex: 1;">
                    <div style="font-weight: 600; margin-bottom: 8px; color: #fff;">
                        🔒 隐私授权提示
                    </div>
                    <div style="line-height: 1.6; color: #94a3b8; font-size: 13px;">
                        为了提供更好的测速体验，我们希望采集您的设备信息用于画像分析。
                        <strong style="color: #60a5fa;">不同意也不会影响测速功能</strong>，
                        我们只会匿名记录页面加载速度等性能数据。您可以随时在页脚撤回授权。
                    </div>
                </div>
                <div style="display: flex; gap: 12px; justify-content: flex-end; flex-shrink: 0;">
                    <button id="consent-deny" style="
                        padding: 10px 20px;
                        background: transparent;
                        color: #94a3b8;
                        border: 1px solid #334155;
                        border-radius: 8px;
                        cursor: pointer;
                        font-size: 13px;
                        transition: all 0.2s;
                    " onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='transparent'">
                        暂不授权
                    </button>
                    <button id="consent-grant" style="
                        padding: 10px 24px;
                        background: linear-gradient(135deg, #3b82f6, #06b6d4);
                        color: #fff;
                        border: none;
                        border-radius: 8px;
                        cursor: pointer;
                        font-size: 13px;
                        font-weight: 600;
                        transition: all 0.2s;
                    " onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                        同意并授权
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(banner);

        document.getElementById('consent-grant').addEventListener('click', grantConsent);
        document.getElementById('consent-deny').addEventListener('click', denyConsent);
    };

    const hideConsentBanner = () => {
        const banner = document.getElementById('privacy-consent-banner');
        if (banner) {
            banner.style.transition = 'opacity 0.3s';
            banner.style.opacity = '0';
            setTimeout(() => banner.remove(), 300);
        }
    };

    const addPrivacyLink = () => {
        const footerInfo = document.querySelector('.grid.grid-cols-1.md\\:grid-cols-3');
        if (footerInfo) {
            const status = getConsentStatus();
            const statusText = status === 1 ? '已授权' : status === 2 ? '未授权' : '未设置';

            const linkDiv = document.createElement('div');
            linkDiv.style.cssText = 'padding: 4px; cursor: pointer;';
            linkDiv.innerHTML = `
                <div id="privacy-status-icon" style="font-size: 24px; margin-bottom: 8px;">${status === 1 ? '✅' : '⚪'}</div>
                <div style="font-weight: bold; color: white; margin-bottom: 4px;">隐私授权</div>
                <div id="privacy-status-text" style="font-size: 12px; color: #64748b;">${statusText}</div>
            `;
            linkDiv.addEventListener('click', () => {
                const currentStatus = getConsentStatus();
                if (currentStatus === 1) {
                    if (confirm('您确定要撤回隐私授权吗？撤回后您的历史数据将被匿名化处理。')) {
                        withdrawConsent();
                        document.getElementById('privacy-status-icon').textContent = '⚪';
                        document.getElementById('privacy-status-text').textContent = '未授权';
                    }
                } else {
                    showConsentBanner();
                }
            });

            const cols = footerInfo.querySelectorAll('div');
            if (cols.length >= 3) {
                cols[2].style.display = 'none';
            }
            footerInfo.appendChild(linkDiv);
        }
    };

    const init = async () => {
        const consentStatus = getConsentStatus();
        const visitorId = getVisitorId();

        if (consentStatus === 0) {
            await collectAnonymous();
            setTimeout(showConsentBanner, 1000);
        } else if (consentStatus === 1) {
            await collectFull();
        } else {
            await collectAnonymous();
        }

        setTimeout(addPrivacyLink, 500);
    };

    window.SpeedProbePrivacy = {
        getConsentStatus,
        grantConsent,
        denyConsent,
        withdrawConsent,
        showConsentBanner
    };

    if (document.readyState === 'complete') {
        init();
    } else {
        window.addEventListener('load', init);
    }
})();
