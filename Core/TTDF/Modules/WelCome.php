<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<script src="https://cdn.tailwindcss.com"></script>
<link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: '#165DFF',
                    secondary: '#00B42A',
                    dark: '#1D2129',
                    light: '#F2F3F5'
                },
                fontFamily: {
                    inter: ['Inter', 'system-ui', 'sans-serif'],
                },
            },
        }
    }
</script>
<style type="text/tailwindcss">
    @layer utilities {
        .h-screen-no-scroll {
            height: 100vh;
            overflow: hidden;
        }
        .min-h-screen-no-scroll {
            min-height: 100vh;
            overflow: hidden;
        }
      .content-auto {
        content-visibility: auto;
      }
      .text-shadow {
        text-shadow: 0 2px 4px rgba(0,0,0,0.1);
      }
      .bg-gradient-primary {
        background: linear-gradient(135deg, #165DFF 0%, #0047CC 100%);
      }
      .bg-glass {
        background: rgba(255, 255, 255, 0.7);
        backdrop-filter: blur(10px);
      }
      .transition-navbar {
        transition: background-color 0.3s, box-shadow 0.3s, padding 0.3s;
      }
    }
</style>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<main>
<section class="min-h-screen-no-scroll relative overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-primary/5 to-primary/10 -z-10"></div>
        <div class="absolute -top-20 -right-20 w-80 h-80 bg-primary/10 rounded-full blur-3xl -z-10"></div>
        <div class="absolute -bottom-40 -left-20 w-96 h-96 bg-secondary/10 rounded-full blur-3xl -z-10"></div>

        <div class="container mx-auto pt-16 pb-16 px-4 md:px-6">
            <div class="max-w-4xl mx-auto text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-xl bg-gradient-primary mb-6 shadow-lg shadow-primary/20">
                    <i class="fa fa-rocket text-white text-2xl"></i>
                </div>
                <h1 class="text-[clamp(2.5rem,5vw,4rem)] font-bold leading-tight text-dark mb-6 text-shadow">
                    最直观的 <span class="text-primary">Typecho</span> 开发框架
                </h1>
                <p class="text-[clamp(1rem,2vw,1.25rem)] text-gray-600 mb-10 max-w-3xl mx-auto">
                    这是 <span class="text-primary">Typecho</span> 的主题模板开发框架, 提供常用的方法以及函数调用
                    <br />Typecho Theme Development Framework
                </p>
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="https://github.com/ShuShuicu/TTDF/blob/master/README.md" target="_blank" class="px-8 py-4 bg-primary text-white rounded-xl hover:bg-primary/90 transition-all shadow-lg hover:shadow-primary/20 hover:shadow-xl text-lg font-medium w-full sm:w-auto">
                        快速开始 <i class="fa fa-arrow-right ml-2"></i>
                    </a>
                    <a href="https://github.com/ShuShuicu/TTDF" target="_blank" class="px-8 py-4 bg-white text-dark border border-gray-200 rounded-xl hover:bg-gray-50 transition-all shadow-md hover:shadow-lg text-lg font-medium w-full sm:w-auto">
                        GitHub <i class="fa fa-brands fa-github ml-2"></i> 
                    </a>
                </div>

                <div class="mt-16 grid grid-cols-2 md:grid-cols-4 gap-4 md:gap-8">
                    <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow">
                        <div class="text-3xl font-bold text-primary mb-2"><?php TTDF::Ver(true) ?></div>
                        <div class="text-gray-500">当前版本</div>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow">
                        <div class="text-3xl font-bold text-primary mb-2">1.2+</div>
                        <div class="text-gray-500">兼容版本</div>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow">
                        <div class="text-3xl font-bold text-primary mb-2">低</div>
                        <div class="text-gray-500">上手难度</div>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-lg hover:shadow-xl transition-shadow">
                        <div class="text-3xl font-bold text-primary mb-2">MIT</div>
                        <div class="text-gray-500">开源许可</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>