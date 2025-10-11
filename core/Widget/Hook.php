<?php
if (!defined('__TYPECHO_ROOT_DIR__')) exit;

class TTDF_Hook
{
    private static $actions = [];
    private static $hook_placeholders = [];
    private static $hook_contents = [];
    private static $executed_hooks = [];
    private static $placeholder_counter = 0;
    private static $buffer_started = false;
    private static $shutdown_registered = false;
    private static $typecho_hooks_registered = false;
    private static $debug_enabled = true;
    private static $debug_log_file = null;
    
    // 映射关系
    private static $typecho_hook_mapping = [
        'Widget_Archive_header' => 'header',
        'Widget_Archive_footer' => 'footer',
        'Widget_Archive_head' => 'load_head',
        'Widget_Archive_foot' => 'load_foot',
        'Widget_Archive_beforeRender' => 'before_render',
        'Widget_Archive_afterRender' => 'after_render',
        'Widget_Contents_Post_beforeRender' => 'post_before_render',
        'Widget_Contents_Post_afterRender' => 'post_after_render',
        'Widget_Contents_Page_beforeRender' => 'page_before_render',
        'Widget_Contents_Page_afterRender' => 'page_after_render',
    ];

    /**
     * 调试日志记录
     */
    private static function debug_log($message, $data = null)
    {
        if (!self::$debug_enabled) {
            return;
        }
        
        if (self::$debug_log_file === null) {
            self::$debug_log_file = __DIR__ . '/../../logs/hook.log';
        }
        
        $timestamp = date('Y-m-d H:i:s.') . substr(microtime(), 2, 3);
        $log_entry = "[{$timestamp}] {$message}";
        
        if ($data !== null) {
            $log_entry .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        
        $log_entry .= "\n";
        
        file_put_contents(self::$debug_log_file, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * 初始化钩子系统
     */
    private static function init()
    {
        self::debug_log("Hook system initializing");
        
        if (!self::$buffer_started) {
            ob_start();
            self::$buffer_started = true;
            self::debug_log("Output buffer started");
        }
        
        if (!self::$shutdown_registered) {
            register_shutdown_function([__CLASS__, 'finalize']);
            self::$shutdown_registered = true;
            self::debug_log("Shutdown function registered");
        }
        
        // 注册 Typecho 钩子监听
        self::registerTypechoHooks();
    }
    
    /**
     * 注册 Typecho 钩子监听
     */
    private static function registerTypechoHooks()
    {
        if (self::$typecho_hooks_registered) {
            return;
        }
        
        // 检查 Typecho_Plugin 类是否存在
        if (!class_exists('Typecho_Plugin')) {
            return;
        }
        
        // 为每个映射的 Typecho 钩子注册监听器
        foreach (self::$typecho_hook_mapping as $typecho_hook => $ttdf_hook) {
            try {
                // 使用正确的 Typecho 钩子注册方式
                Typecho_Plugin::factory($typecho_hook)->trigger = function() use ($ttdf_hook) {
                    // 获取传递给钩子的参数
                    $args = func_get_args();
                    
                    // 触发对应的 TTDF 钩子
                    self::do_action($ttdf_hook, $args);
                };
            } catch (Exception $e) {
                // 忽略不存在的钩子
                continue;
            }
        }
        
        self::$typecho_hooks_registered = true;
    }

    /**
     * 注册钩子
     * @param string $hook_name 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级（数字越小优先级越高）
     */
    public static function add_action($hook_name, $callback, $priority = 10)
    {
        self::init();
        
        $callback_info = is_string($callback) ? $callback : (is_array($callback) ? implode('::', $callback) : 'closure');
        $is_already_executed = isset(self::$executed_hooks[$hook_name]);
        self::debug_log("Registering hook: {$hook_name}", [
            'callback' => $callback_info,
            'priority' => $priority,
            'already_executed' => $is_already_executed,
            'current_actions_count' => isset(self::$actions[$hook_name]) ? count(self::$actions[$hook_name], COUNT_RECURSIVE) - count(self::$actions[$hook_name]) : 0
        ]);
        
        if (!isset(self::$actions[$hook_name])) {
            self::$actions[$hook_name] = [];
        }
        
        if (!isset(self::$actions[$hook_name][$priority])) {
            self::$actions[$hook_name][$priority] = [];
        }
        
        self::$actions[$hook_name][$priority][] = $callback;
        
        // 如果钩子已经执行过，收集新注册回调的内容
        if ($is_already_executed) {
            self::debug_log("Hook {$hook_name} already executed, collecting late-registered callback content");
            ob_start();
            call_user_func($callback, self::$executed_hooks[$hook_name]);
            $content = ob_get_clean();
            
            // 将内容添加到对应钩子的内容收集中
            if (!isset(self::$hook_contents[$hook_name])) {
                self::$hook_contents[$hook_name] = '';
            }
            self::$hook_contents[$hook_name] .= $content;
            
            self::debug_log("Late-registered callback content collected", [
                'hook_name' => $hook_name,
                'content_length' => strlen($content),
                'total_content_length' => strlen(self::$hook_contents[$hook_name])
            ]);
        }
    }

    /**
     * 执行钩子
     * @param string $hook_name 钩子名称
     * @param mixed $args 传递给回调函数的参数
     * @param bool $return_content 是否返回内容而不是直接输出
     */
    public static function do_action($hook_name, $args = null, $return_content = false)
    {
        self::init();
        
        $registered_callbacks_count = 0;
        if (isset(self::$actions[$hook_name])) {
            foreach (self::$actions[$hook_name] as $priority => $callbacks) {
                $registered_callbacks_count += count($callbacks);
            }
        }
        
        // 记录执行参数
        $was_already_executed = isset(self::$executed_hooks[$hook_name]);
        self::$executed_hooks[$hook_name] = $args;
        
        self::debug_log("Executing hook: {$hook_name}", [
            'args' => $args,
            'return_content' => $return_content,
            'registered_callbacks_count' => $registered_callbacks_count,
            'already_executed' => $was_already_executed
        ]);
        
        // 收集当前已注册的钩子内容
        ob_start();
        $executed_callbacks = 0;
        if (isset(self::$actions[$hook_name])) {
            ksort(self::$actions[$hook_name]);
            foreach (self::$actions[$hook_name] as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    $callback_info = is_string($callback) ? $callback : (is_array($callback) ? implode('::', $callback) : 'closure');
                    self::debug_log("Executing callback for {$hook_name}", [
                        'callback' => $callback_info,
                        'priority' => $priority
                    ]);
                    call_user_func($callback, $args);
                    $executed_callbacks++;
                }
            }
        }
        $content = ob_get_clean();
        
        self::debug_log("Hook execution completed: {$hook_name}", [
            'executed_callbacks' => $executed_callbacks,
            'content_length' => strlen($content)
        ]);
        
        // 初始化钩子内容收集
        if (!isset(self::$hook_contents[$hook_name])) {
            self::$hook_contents[$hook_name] = '';
        }
        self::$hook_contents[$hook_name] .= $content;
        
        if ($return_content) {
            self::debug_log("Returning content for {$hook_name}", [
                'total_content_length' => strlen(self::$hook_contents[$hook_name])
            ]);
            return self::$hook_contents[$hook_name];
        } else {
            // 生成唯一占位符
            $placeholder = '<!--TTDF_HOOK_' . $hook_name . '_' . (++self::$placeholder_counter) . '-->';
            self::$hook_placeholders[$placeholder] = $hook_name;
            self::debug_log("Generated placeholder for {$hook_name}", [
                'placeholder' => $placeholder,
                'total_placeholders' => count(self::$hook_placeholders)
            ]);
            echo $placeholder;
        }
    }

    /**
     * 获取钩子内容（不执行，只返回）
     * @param string $hook_name 钩子名称
     * @param mixed $args 传递给回调函数的参数
     * @return string
     */
    public static function get_hook_content($hook_name, $args = null)
    {
        return self::do_action($hook_name, $args, true);
    }
    
    /**
     * 检查钩子是否已执行
     * @param string $hook_name 钩子名称
     * @return bool
     */
    public static function has_executed($hook_name)
    {
        return isset(self::$executed_hooks[$hook_name]);
    }
    
    /**
     * 移除钩子
     * @param string $hook_name 钩子名称
     * @param callable $callback 回调函数
     * @param int $priority 优先级
     */
    public static function remove_action($hook_name, $callback, $priority = 10)
    {
        if (isset(self::$actions[$hook_name][$priority])) {
            $key = array_search($callback, self::$actions[$hook_name][$priority], true);
            if ($key !== false) {
                unset(self::$actions[$hook_name][$priority][$key]);
            }
        }
    }

    /**
     * 自动在脚本结束时调用
     */
    public static function finalize()
    {
        self::debug_log("Finalize started", [
            'buffer_started' => self::$buffer_started,
            'ob_level' => ob_get_level(),
            'total_placeholders' => count(self::$hook_placeholders),
            'executed_hooks' => array_keys(self::$executed_hooks)
        ]);
        
        if (self::$buffer_started && ob_get_level() > 0) {
            $output = ob_get_clean();
            self::debug_log("Output buffer cleaned", ['output_length' => strlen($output)]);
            
            // 在最终输出前，再次执行所有已注册但可能在占位符生成后才注册的钩子
            foreach (self::$hook_placeholders as $placeholder => $hook_name) {
                $current_callbacks_count = 0;
                if (isset(self::$actions[$hook_name])) {
                    foreach (self::$actions[$hook_name] as $priority => $callbacks) {
                        $current_callbacks_count += count($callbacks);
                    }
                }
                
                self::debug_log("Processing placeholder: {$placeholder}", [
                    'hook_name' => $hook_name,
                    'current_callbacks_count' => $current_callbacks_count,
                    'has_executed_hooks' => isset(self::$executed_hooks[$hook_name]),
                    'current_content_length' => isset(self::$hook_contents[$hook_name]) ? strlen(self::$hook_contents[$hook_name]) : 0
                ]);
                
                // 确保钩子内容是最新的
                if (isset(self::$actions[$hook_name]) && isset(self::$executed_hooks[$hook_name])) {
                    // 重新收集钩子内容，确保包含所有后注册的回调
                    ob_start();
                    $fresh_executed_callbacks = 0;
                    ksort(self::$actions[$hook_name]);
                    foreach (self::$actions[$hook_name] as $priority => $callbacks) {
                        foreach ($callbacks as $callback) {
                            $callback_info = is_string($callback) ? $callback : (is_array($callback) ? implode('::', $callback) : 'closure');
                            self::debug_log("Re-executing callback in finalize", [
                                'hook_name' => $hook_name,
                                'callback' => $callback_info,
                                'priority' => $priority
                            ]);
                            call_user_func($callback, self::$executed_hooks[$hook_name]);
                            $fresh_executed_callbacks++;
                        }
                    }
                    $fresh_content = ob_get_clean();
                    
                    self::debug_log("Fresh content collected in finalize", [
                        'hook_name' => $hook_name,
                        'fresh_executed_callbacks' => $fresh_executed_callbacks,
                        'fresh_content_length' => strlen($fresh_content),
                        'old_content_length' => isset(self::$hook_contents[$hook_name]) ? strlen(self::$hook_contents[$hook_name]) : 0
                    ]);
                    
                    // 更新钩子内容
                    self::$hook_contents[$hook_name] = $fresh_content;
                }
            }
            
            // 替换所有占位符为实际内容
            foreach (self::$hook_placeholders as $placeholder => $hook_name) {
                $replacement_content = '';
                if (isset(self::$hook_contents[$hook_name])) {
                    $replacement_content = self::$hook_contents[$hook_name];
                }
                
                self::debug_log("Replacing placeholder", [
                    'placeholder' => $placeholder,
                    'hook_name' => $hook_name,
                    'replacement_length' => strlen($replacement_content),
                    'found_in_output' => strpos($output, $placeholder) !== false
                ]);
                
                $output = str_replace($placeholder, $replacement_content, $output);
            }
            
            self::debug_log("Final output prepared", [
                'final_output_length' => strlen($output),
                'placeholders_processed' => count(self::$hook_placeholders)
            ]);
            
            echo $output;
            self::$buffer_started = false;
        }
        
        self::debug_log("Finalize completed");
    }

    /**
     * 清理钩子数据
     */
    public static function clear()
    {
        self::debug_log("Clearing hook data");
        
        self::$actions = [];
        self::$hook_placeholders = [];
        self::$hook_contents = [];
        self::$executed_hooks = [];
        self::$placeholder_counter = 0;
        
        if (self::$buffer_started && ob_get_level() > 0) {
            ob_end_clean();
            self::$buffer_started = false;
        }
        
        self::debug_log("Hook data cleared");
    }
    
    /**
     * 启用或禁用调试日志
     */
    public static function set_debug($enabled = true)
    {
        self::$debug_enabled = $enabled;
        if ($enabled) {
            self::debug_log("Debug logging enabled");
        }
    }
    
    /**
     * 获取调试日志文件路径
     */
    public static function get_debug_log_file()
    {
        if (self::$debug_log_file === null) {
            self::$debug_log_file = __DIR__ . '/../../logs/hook.log';
        }
        return self::$debug_log_file;
    }
}

