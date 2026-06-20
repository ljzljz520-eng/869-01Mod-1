<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>探针管理后台</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <script src="https://unpkg.com/vue@3/dist/vue.global.js"></script>
</head>

<body class="bg-gray-100 min-h-screen text-gray-800">
    <div id="app" class="pb-10">
        <!-- 头部 -->
        <header class="bg-white shadow">
            <div
                class="container mx-auto px-4 md:px-6 py-4 flex flex-col md:flex-row justify-between items-center space-y-3 md:space-y-0">
                <div class="flex items-center space-x-2">
                    <i class="ri-radar-fill text-blue-600 text-2xl"></i>
                    <h1 class="text-xl font-bold font-sans">探针管理后台</h1>
                    <span class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-700 text-xs rounded-full">隐私合规版</span>
                </div>
                <div class="text-sm text-gray-500 flex items-center space-x-4">
                    <span>当前时间: {{ currentTime }}</span>
                    <a href="/" target="_blank" class="text-blue-600 hover:text-blue-800 font-medium">访问前台</a>
                </div>
            </div>
        </header>

        <!-- 主体内容 -->
        <main class="container mx-auto px-4 md:px-6 py-6 md:py-8">

            <!-- 统计卡片 -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-gray-500 text-xs mb-1">总访问量</div>
                    <div class="text-xl font-bold">{{ stats.total }}</div>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-gray-500 text-xs mb-1">今日访问</div>
                    <div class="text-xl font-bold text-blue-600">{{ stats.today }}</div>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-gray-500 text-xs mb-1">已授权</div>
                    <div class="text-xl font-bold text-green-600">{{ stats.consented }}</div>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-gray-500 text-xs mb-1">已匿名化</div>
                    <div class="text-xl font-bold text-gray-500">{{ stats.anonymized }}</div>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-gray-500 text-xs mb-1">慢速页面</div>
                    <div class="text-xl font-bold text-orange-600">{{ stats.slow_pages }}</div>
                </div>
                <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100">
                    <div class="text-gray-500 text-xs mb-1">平均加载</div>
                    <div class="text-xl font-bold text-cyan-600">{{ stats.avg_load_time }}ms</div>
                </div>
            </div>

            <!-- 隐私合规提示 -->
            <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 flex items-start space-x-3">
                <i class="ri-shield-check-line text-blue-600 text-xl mt-0.5"></i>
                <div class="text-sm text-blue-800">
                    <div class="font-semibold mb-1">隐私保护已启用</div>
                    <div class="text-blue-700">
                        未授权访客仅记录页面加载速度等匿名性能数据；已授权访客可查看完整画像；
                        撤回授权后历史数据将自动匿名化，仅保留汇总统计数字。
                    </div>
                </div>
            </div>

            <!-- 工具栏 -->
            <div class="flex flex-col md:flex-row justify-between items-center mb-6 space-y-4 md:space-y-0">
                <div class="relative w-full md:w-96">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                        <i class="ri-search-line text-gray-400"></i>
                    </span>
                    <input type="text" v-model="searchQuery" @keyup.enter="fetchData(1)"
                        class="w-full py-2 pl-10 pr-4 text-gray-700 bg-white border rounded-lg focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-200"
                        placeholder="搜索备注 / 已授权的 IP 和城市...">
                </div>
                <div class="flex space-x-2">
                    <button @click="exportData"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        <i class="ri-download-line mr-1"></i> 导出 CSV
                    </button>
                    <button @click="fetchData(1)"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                        <i class="ri-refresh-line mr-1"></i> 刷新
                    </button>
                </div>
            </div>

            <!-- 数据表格 -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-gray-600 text-sm tracking-wider whitespace-nowrap">
                                <th class="px-6 py-4 font-semibold">ID</th>
                                <th class="px-6 py-4 font-semibold">隐私状态</th>
                                <th class="px-6 py-4 font-semibold">IP / 位置</th>
                                <th class="px-6 py-4 font-semibold">设备信息</th>
                                <th class="px-6 py-4 font-semibold">页面性能</th>
                                <th class="px-6 py-4 font-semibold">备注</th>
                                <th class="px-6 py-4 font-semibold">访问时间</th>
                                <th class="px-6 py-4 font-semibold text-right">操作</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            <tr v-for="item in visitors" :key="item.id"
                                class="hover:bg-gray-50 transition whitespace-nowrap">
                                <td class="px-6 py-4 text-gray-500">#{{ item.id }}</td>
                                <td class="px-6 py-4">
                                    <span v-if="item.is_anonymized == 1"
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                        <i class="ri-eye-off-line mr-1"></i>已匿名
                                    </span>
                                    <span v-else-if="item.privacy_consent == 1"
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-700">
                                        <i class="ri-shield-check-line mr-1"></i>已授权
                                    </span>
                                    <span v-else
                                        class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                                        <i class="ri-shield-line mr-1"></i>未授权
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-gray-900">
                                        {{ item.ip || '-' }}
                                        <span v-if="item.is_anonymized == 1 || item.privacy_consent != 1"
                                            class="ml-1 text-xs text-gray-400">(脱敏)</span>
                                    </div>
                                    <div class="text-xs text-gray-500">{{ item.country }} {{ item.city }}</div>
                                    <div class="text-xs text-gray-400">{{ item.isp }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="item.is_anonymized == 1 || item.privacy_consent != 1"
                                        class="text-sm text-gray-400">
                                        <i class="ri-lock-line"></i> 未授权画像
                                    </div>
                                    <div v-else class="text-sm">
                                        <i :class="getDeviceIcon(item)"></i>
                                        {{ item.os }} / {{ item.browser }}
                                    </div>
                                    <div v-if="item.is_anonymized == 1 || item.privacy_consent != 1"
                                        class="text-xs text-gray-300 mt-1">
                                        -
                                    </div>
                                    <div v-else class="text-xs text-gray-400 mt-1">
                                        {{ item.screen_width }}x{{ item.screen_height }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="item.page_load_time"
                                        :class="['text-sm font-medium', item.page_load_time > 2000 ? 'text-orange-600' : 'text-green-600']">
                                        {{ item.page_load_time }}ms
                                    </div>
                                    <div v-else class="text-sm text-gray-300">-</div>
                                    <div v-if="item.page_size" class="text-xs text-gray-400">
                                        {{ formatFileSize(item.page_size) }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div v-if="item.is_anonymized == 1"
                                        class="text-sm text-gray-300 italic">
                                        <i class="ri-eye-off-line"></i> 已清除
                                    </div>
                                    <div v-else-if="item.remark"
                                        class="text-sm text-gray-800 bg-yellow-50 px-2 py-1 rounded inline-block">
                                        {{ item.remark }}
                                    </div>
                                    <div v-else class="text-sm text-gray-300 italic">无</div>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    {{ item.created_at }}
                                </td>
                                <td class="px-6 py-4 text-right space-x-2">
                                    <button @click="openDetail(item)"
                                        class="text-blue-600 hover:text-blue-800 text-sm">详情</button>
                                    <button v-if="item.is_anonymized != 1" @click="editRemark(item)"
                                        class="text-gray-600 hover:text-gray-800 text-sm">备注</button>
                                    <button v-if="item.privacy_consent == 1 && item.is_anonymized != 1"
                                        @click="withdrawConsent(item)"
                                        class="text-red-600 hover:text-red-800 text-sm">撤回</button>
                                </td>
                            </tr>
                            <tr v-if="visitors.length === 0">
                                <td colspan="8" class="px-6 py-12 text-center text-gray-400">
                                    暂无数据
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- 分页 -->
                <div class="px-6 py-4 bg-gray-50 border-t border-gray-100 flex justify-between items-center">
                    <div class="text-sm text-gray-500">
                        共 {{ total }} 条记录，第 {{ page }} / {{ totalPages }} 页
                    </div>
                    <div class="flex space-x-2">
                        <button @click="prevPage" :disabled="page <= 1"
                            class="px-3 py-1 bg-white border rounded hover:bg-gray-100 disabled:opacity-50">上一页</button>
                        <button @click="nextPage" :disabled="page >= totalPages"
                            class="px-3 py-1 bg-white border rounded hover:bg-gray-100 disabled:opacity-50">下一页</button>
                    </div>
                </div>
            </div>

        </main>

        <!-- 详情弹窗 -->
        <div v-if="showDetailModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="showDetailModal = false">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl max-h-[90vh] flex flex-col">
                <div class="px-6 py-4 border-b flex justify-between items-center bg-gray-50 flex-shrink-0">
                    <div class="flex items-center space-x-3">
                        <h3 class="text-lg font-bold">访客详情 #{{ currentItem.id }}</h3>
                        <span v-if="currentItem.is_anonymized == 1"
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                            已匿名
                        </span>
                        <span v-else-if="currentItem.privacy_consent == 1"
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">
                            已授权
                        </span>
                        <span v-else
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-700">
                            未授权
                        </span>
                    </div>
                    <button @click="showDetailModal = false" class="text-gray-400 hover:text-gray-600"><i
                            class="ri-close-line text-xl"></i></button>
                </div>
                <div class="flex-1 overflow-y-auto">
                    <div v-if="currentItem.is_anonymized == 1 || currentItem.privacy_consent != 1"
                        class="mx-6 mt-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
                        <i class="ri-information-line mr-1"></i>
                        该访客{{ currentItem.is_anonymized == 1 ? '已撤回授权，数据已匿名化' : '未授权，仅显示匿名数据' }}。
                        敏感字段已做脱敏处理，仅保留汇总统计信息。
                    </div>
                    <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                        <div v-for="field in visibleDetailFields" :key="field.key"
                            class="border-b border-gray-100 py-2">
                            <span class="text-gray-500 block mb-1 text-xs">{{ field.label }}</span>
                            <span class="font-mono text-gray-800 break-all">{{ formatValue(currentItem[field.key])
                                }}</span>
                        </div>
                    </div>
                    <div class="px-6 py-4 bg-amber-50 border-t border-amber-100 text-xs text-amber-700 space-y-1">
                        <div class="font-semibold mb-2"><i class="ri-information-line mr-1"></i>数据说明</div>
                        <div>• <b>系统版本</b>：Chrome 90+ 将 macOS 版本冻结为 10.15.7，无法获取真实版本</div>
                        <div>• <b>设备内存</b>：浏览器返回模糊值（如 4/8GB），非精确值</div>
                        <div>• <b>网络类型</b>：仅显示等效网速，无法检测 WiFi/有线/代理</div>
                        <div>• <b>IP 定位</b>：本地/内网 IP 无法定位，需部署到公网服务器</div>
                        <div class="text-amber-600 mt-2">以上为浏览器隐私保护机制限制，非采集错误。</div>
                    </div>
                </div>
                <div class="px-6 py-4 border-t bg-gray-50 flex justify-between items-center flex-shrink-0">
                    <div v-if="currentItem.privacy_consent == 1 && currentItem.is_anonymized != 1">
                        <button @click="withdrawConsent(currentItem)"
                            class="px-4 py-2 text-red-600 hover:bg-red-50 rounded text-sm">
                            <i class="ri-shield-cross-line mr-1"></i> 撤回授权
                        </button>
                    </div>
                    <div></div>
                    <button @click="showDetailModal = false"
                        class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">关闭</button>
                </div>
            </div>
        </div>

        <!-- 备注弹窗 -->
        <div v-if="showRemarkModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4"
            @click.self="showRemarkModal = false">
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">
                <div class="px-6 py-4 border-b">
                    <h3 class="text-lg font-bold">编辑备注</h3>
                </div>
                <div class="p-6">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">姓名 / 备注信息</label>
                        <textarea v-model="remarkForm.remark" rows="3"
                            class="w-full border rounded-lg p-3 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                        <p class="mt-2 text-xs text-gray-500">
                            <i class="ri-information-line"></i> 备注信息属于个人敏感信息，撤回授权后将自动清除。
                        </p>
                    </div>
                </div>
                <div class="px-6 py-4 border-t bg-gray-50 flex justify-end space-x-3">
                    <button @click="showRemarkModal = false"
                        class="px-4 py-2 text-gray-600 hover:bg-gray-100 rounded">取消</button>
                    <button @click="saveRemark"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">保存</button>
                </div>
            </div>
        </div>

    </div>

    <script>
        const { createApp, ref, computed, onMounted } = Vue;

        createApp({
            setup() {
                const visitors = ref([]);
                const total = ref(0);
                const page = ref(1);
                const totalPages = ref(1);
                const searchQuery = ref('');
                const stats = ref({
                    total: 0, today: 0, consented: 0, anonymized: 0,
                    slow_pages: 0, avg_load_time: 0
                });

                const showDetailModal = ref(false);
                const showRemarkModal = ref(false);
                const currentItem = ref({});
                const remarkForm = ref({ id: null, remark: '' });
                const currentTime = ref('');

                const allDetailFields = [
                    { key: 'id', label: 'ID', sensitive: false },
                    { key: 'privacy_consent', label: '隐私授权状态', sensitive: false },
                    { key: 'consent_time', label: '授权时间', sensitive: false },
                    { key: 'withdraw_time', label: '撤回时间', sensitive: false },
                    { key: 'is_anonymized', label: '是否匿名化', sensitive: false },
                    { key: 'ip', label: 'IP 地址', sensitive: true },
                    { key: 'country', label: '国家', sensitive: true },
                    { key: 'city', label: '城市', sensitive: true },
                    { key: 'isp', label: '运营商', sensitive: true },
                    { key: 'user_agent', label: '用户代理', sensitive: true },
                    { key: 'browser', label: '浏览器', sensitive: false },
                    { key: 'browser_version', label: '浏览器版本', sensitive: false },
                    { key: 'os', label: '操作系统', sensitive: false },
                    { key: 'os_version', label: '系统版本', sensitive: false },
                    { key: 'device_type', label: '设备类型', sensitive: false },
                    { key: 'screen_width', label: '屏幕宽度', sensitive: false },
                    { key: 'screen_height', label: '屏幕高度', sensitive: false },
                    { key: 'window_width', label: '窗口宽度', sensitive: false },
                    { key: 'window_height', label: '窗口高度', sensitive: false },
                    { key: 'language', label: '语言偏好', sensitive: false },
                    { key: 'timezone', label: '时区', sensitive: false },
                    { key: 'platform', label: '平台', sensitive: false },
                    { key: 'cookie_enabled', label: 'Cookie 状态', sensitive: false },
                    { key: 'touch_points', label: '触控点数', sensitive: false },
                    { key: 'device_memory', label: '设备内存 (GB)', sensitive: false },
                    { key: 'cpu_cores', label: 'CPU 核心数', sensitive: false },
                    { key: 'connection_type', label: '网络类型', sensitive: false },
                    { key: 'referrer', label: '来源页面', sensitive: true },
                    { key: 'remark', label: '备注', sensitive: true },
                    { key: 'page_load_time', label: '页面加载时间 (ms)', sensitive: false },
                    { key: 'page_size', label: '页面大小 (KB)', sensitive: false },
                    { key: 'created_at', label: '访问时间', sensitive: false }
                ];

                const visibleDetailFields = computed(() => {
                    if (!currentItem.value) return allDetailFields;
                    if (currentItem.value.is_anonymized == 1 || currentItem.value.privacy_consent != 1) {
                        return allDetailFields.filter(f => !f.sensitive || f.key === 'remark');
                    }
                    return allDetailFields;
                });

                const fetchStats = async () => {
                    try {
                        const res = await fetch('/api.php?action=stats');
                        const json = await res.json();
                        if (json.status === 'success') {
                            stats.value = {
                                total: json.total,
                                today: json.today,
                                consented: json.consented,
                                anonymized: json.anonymized,
                                slow_pages: json.slow_pages,
                                avg_load_time: json.avg_load_time
                            };
                        }
                    } catch (e) {
                        console.error(e);
                    }
                };

                const fetchData = async (p = 1) => {
                    try {
                        const res = await fetch(`/api.php?action=list&page=${p}&search=${encodeURIComponent(searchQuery.value)}`);
                        const json = await res.json();
                        if (json.status === 'success') {
                            visitors.value = json.data;
                            total.value = json.total;
                            page.value = json.page;
                            totalPages.value = json.pages;
                        }
                    } catch (e) {
                        console.error(e);
                    }
                    fetchStats();
                };

                const prevPage = () => {
                    if (page.value > 1) fetchData(page.value - 1);
                };

                const nextPage = () => {
                    if (page.value < totalPages.value) fetchData(page.value + 1);
                };

                const openDetail = (item) => {
                    currentItem.value = item;
                    showDetailModal.value = true;
                };

                const editRemark = (item) => {
                    remarkForm.value = { id: item.id, remark: item.remark || '' };
                    showRemarkModal.value = true;
                };

                const saveRemark = async () => {
                    try {
                        const res = await fetch('/api.php?action=remark', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(remarkForm.value)
                        });
                        const json = await res.json();
                        if (json.status === 'success') {
                            showRemarkModal.value = false;
                            fetchData(page.value);
                        } else {
                            alert('保存失败: ' + (json.message || '未知错误'));
                        }
                    } catch (e) {
                        alert('错误: ' + e.message);
                    }
                };

                const withdrawConsent = async (item) => {
                    if (!confirm(`确定要撤回访客 #${item.id} 的隐私授权吗？\n\n撤回后该访客的个人信息将被匿名化处理，仅保留汇总统计数据，且此操作不可恢复。`)) {
                        return;
                    }
                    try {
                        const res = await fetch('/api.php?action=consent', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: item.id, grant: false })
                        });
                        const json = await res.json();
                        if (json.status === 'success') {
                            showDetailModal.value = false;
                            fetchData(page.value);
                            alert('已成功撤回授权，数据已匿名化处理。');
                        } else {
                            alert('操作失败: ' + (json.message || '未知错误'));
                        }
                    } catch (e) {
                        alert('错误: ' + e.message);
                    }
                };

                const exportData = () => {
                    const search = encodeURIComponent(searchQuery.value);
                    window.location.href = `/api.php?action=export&search=${search}`;
                };

                const getDeviceIcon = (item) => {
                    const os = (item.os || '').toLowerCase();
                    if (os.includes('mac') || os.includes('windows') || os.includes('linux')) return 'ri-computer-line';
                    if (os.includes('android') || os.includes('ios') || os.includes('ipad')) return 'ri-smartphone-line';
                    return 'ri-device-line';
                };

                const formatFileSize = (kb) => {
                    if (!kb) return '-';
                    if (kb < 1024) return kb + ' KB';
                    return (kb / 1024).toFixed(2) + ' MB';
                };

                const formatValue = (val, key) => {
                    if (val === null || val === undefined || val === '') return '-';
                    if (val === 0) return '0';
                    if (key === 'privacy_consent') {
                        const map = { 0: '未授权', 1: '已授权', 2: '已撤回' };
                        return map[val] || '未知';
                    }
                    if (key === 'is_anonymized') {
                        return val == 1 ? '是' : '否';
                    }
                    return val;
                };

                setInterval(() => {
                    const now = new Date();
                    const hours = String(now.getHours()).padStart(2, '0');
                    const minutes = String(now.getMinutes()).padStart(2, '0');
                    const seconds = String(now.getSeconds()).padStart(2, '0');
                    currentTime.value = `${hours}:${minutes}:${seconds}`;
                }, 1000);

                onMounted(() => {
                    fetchData();
                    fetchStats();
                });

                return {
                    visitors, total, page, totalPages, searchQuery, stats,
                    showDetailModal, showRemarkModal, currentItem, remarkForm, currentTime,
                    allDetailFields, visibleDetailFields,
                    fetchData, prevPage, nextPage, openDetail, editRemark, saveRemark,
                    withdrawConsent, exportData, getDeviceIcon, formatValue, formatFileSize
                };
            }
        }).mount('#app');
    </script>
</body>

</html>