<?php

class TemplateHandler {
    public static function handle($action, $method, $uri_parts) {
        $user_id = checkAuth();
        
        if ($method === 'GET' && empty($action)) {
            // Get templates
            $templates = [
                ['id' => 1, 'title' => 'Lista della Spesa', 'description' => 'Template per la spesa settimanale'],
                ['id' => 2, 'title' => 'Checklist Viaggio', 'description' => 'Tutto quello che serve per un viaggio'],
                ['id' => 3, 'title' => 'Task Lavorativi', 'description' => 'Organizza i tuoi task di lavoro']
            ];
            
            sendJson(true, $templates);
        } else {
            sendJson(true, ['message' => 'Template handler partially implemented']);
        }
    }
}

?>