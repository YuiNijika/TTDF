<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;
?>
<main>
    <section class="min-h-screen-no-scroll">
        <div class="background-gradient"></div>
        <div class="blob-primary"></div>
        <div class="blob-secondary"></div>

        <div class="container">
            <div class="text-center max-w-4xl mx-auto">
                <div class="icon-container">
                    <svg t="1755579480924" class="icon" viewBox="0 0 1024 1024" version="1.1"
                        xmlns="http://www.w3.org/2000/svg" p-id="4927">
                        <path
                            d="M512 1024C132.647 1024 0 891.313 0 512S132.647 0 512 0s512 132.687 512 512-132.647 512-512 512zM236.308 354.462h551.384v-78.77H236.308v78.77z m0 196.923h393.846v-78.77H236.308v78.77z m0 196.923h472.615v-78.77H236.308v78.77z"
                            p-id="4928" fill="#2c2c2c"></path>
                    </svg>
                </div>
                <h1>
                    最直观的 <span class="text-primary">Typecho</span> 开发框架
                </h1>
                <p class="max-w-3xl mx-auto">
                    这是 <span class="text-primary">Typecho</span> 的主题模板开发框架, 提供常用的方法以及函数调用
                    <br />Typecho Theme Development Framework
                </p>

                <div class="button-group">
                    <a href="https://typecho.dev/develop/quickstart.html" target="_blank" class="button button-primary">
                        快速开始
                        <svg class="icon-arrow" viewBox="0 0 24 24">
                            <path d="M5 12h14M12 5l7 7-7 7" />
                        </svg>
                    </a>
                    <a href="https://github.com/YuiNijika/TTDF" target="_blank" class="button button-secondary">
                        GitHub
                        <svg class="icon-github" viewBox="0 0 24 24">
                            <path d="M12 2C6.477 2 2 6.477 2 12c0 4.418 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.462-1.11-1.462-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.836c.85.004 1.705.114 2.504.336 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.578.688.48C19.138 20.163 22 16.418 22 12c0-5.523-4.477-10-10-10z" />
                        </svg>
                    </a>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php TTDF::Ver(true); ?></div>
                        <div class="stat-label">当前版本</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">1.2+</div>
                        <div class="stat-label">兼容版本</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">低</div>
                        <div class="stat-label">上手难度</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">MIT</div>
                        <div class="stat-label">开源许可</div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>