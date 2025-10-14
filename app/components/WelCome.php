<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<main class="antialiased bg-white dark:bg-[#020420] dark:text-white flex flex-col font-sans items-center justify-center min-h-screen overflow-hidden relative text-[#020420] text-center">
    <a href="https://github.com/YuiNijika/TTDF" target="_blank" rel="noopener" class="flex flex-col items-center justify-center gap-4 group ttdf-logo" id="ttdfImg">
        <div class="relative flex justify-center">
            <svg t="1760471219873" width="80" class="triangle-loading" viewBox="0 0 1109 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="4538">
                <path
                    d="M438.044444 85.333333C317.866667 104.533333 244.622222 143.644444 199.111111 213.333333c-49.777778 78.222222-64 142.933333-64.711111 295.111111-0.711111 263.822222 71.822222 379.022222 264.533333 420.977778 71.111111 14.933333 260.266667 14.933333 332.088889 0 150.755556-32.711111 227.555556-113.777778 254.577778-268.8 14.933333-85.333333 10.666667-278.755556-7.822222-344.177778-34.133333-120.888889-99.555556-185.6-220.444445-216.888888-65.422222-17.066667-248.177778-24.888889-319.288889-14.222223zM796.444444 344.888889V376.888889H334.222222v-64h462.222222v32zM665.6 506.311111c1.422222 14.222222-0.711111 28.444444-3.555556 30.577778-3.555556 2.133333-78.222222 4.266667-165.688888 4.266667l-158.577778 1.422222-2.133334-33.422222-2.133333-32.711112 164.977778 1.422223 164.977778 2.133333 2.133333 26.311111z m66.844444 172.8v32H334.222222v-64h398.222222v32z"
                    p-id="4539" />
            </svg>
        </div>
        <div class="flex flex-col items-center">
            <svg xmlns="http://www.w3.org/2000/svg" width="214" height="53" fill="none" class="dark:group-hover:text-white dark:text-gray-200 group-hover:text-[#020420] text-[#020420]/80 mb-2" viewBox="0 0 1890 1417">
                <text id="TTDF" class="cls-1" transform="matrix(5.228, 0, 0, 5.228, 0, 952.004)">TTDF</text>
            </svg>
            <div class="flex gap-2" style="margin-top: 10px;">
                <span class="bg-[#00DC42]/10 border border-[#00DC42]/50 font-mono font-semibold group-hover:bg-[#00DC42]/15 group-hover:border-[#00DC42] inline-block leading-none px-2.5 py-1.5 rounded text-[#00DC82] text-[16px]">TTDF</span>
                <span class="bg-[#00DC42]/10 border border-[#00DC42]/50 font-mono font-semibold group-hover:bg-[#00DC42]/15 group-hover:border-[#00DC42] inline-block leading-none px-2.5 py-1.5 rounded text-[#00DC82] text-[16px]"><?php TTDF::Ver(true) ?></span>
            </div>
        </div>
    </a>
    <div class="ttdf-loader-bar"></div>
</main>