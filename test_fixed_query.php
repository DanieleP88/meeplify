<?php
require_once 'app/lib/Config.php';
require_once 'app/lib/DB.php';

try {
    $pdo = DB::getPDO();
    
    echo "🧪 Testing fixed query...\n\n";
    
    // Test the updated query with correct column names
    $stmt = $pdo->prepare("
        SELECT c.id, c.title, c.description, c.created_at, c.updated_at,
               COUNT(DISTINCT s.id) as section_count,
               COUNT(DISTINCT i.id) as item_count,
               COUNT(DISTINCT CASE WHEN i.is_done = 1 THEN i.id END) as completed_count
        FROM checklists c
        LEFT JOIN sections s ON c.id = s.checklist_id
        LEFT JOIN items i ON c.id = i.checklist_id
        WHERE c.user_id = ? AND c.deleted_at IS NULL
        GROUP BY c.id
        ORDER BY c.updated_at DESC
        LIMIT 20
    ");
    
    $stmt->execute([1]); // Test with user_id 1
    $checklists = $stmt->fetchAll();
    
    echo "✅ Query executed successfully!\n";
    echo "📊 Found " . count($checklists) . " checklist(s)\n\n";
    
    if (count($checklists) > 0) {
        echo "📝 Sample data:\n";
        foreach ($checklists as $checklist) {
            echo "   - ID: {$checklist['id']}, Title: {$checklist['title']}\n";
            echo "     Items: {$checklist['item_count']}, Completed: {$checklist['completed_count']}\n";
        }
    } else {
        echo "📝 No checklists found. Creating test data...\n";
        
        // Create test checklist
        $stmt = $pdo->prepare("INSERT INTO checklists (title, description, user_id) VALUES (?, ?, ?)");
        $stmt->execute(['My First Checklist', 'Test checklist created by fix', 1]);
        $checklist_id = $pdo->lastInsertId();
        
        echo "   ✅ Created test checklist with ID: $checklist_id\n";
        
        // Create test section
        $stmt = $pdo->prepare("INSERT INTO sections (checklist_id, name, sort_index) VALUES (?, ?, ?)");
        $stmt->execute([$checklist_id, 'Getting Started', 0]);
        $section_id = $pdo->lastInsertId();
        
        echo "   ✅ Created test section with ID: $section_id\n";
        
        // Create test items
        $items = [
            'Complete setup',
            'Test the application', 
            'Create first real checklist'
        ];
        
        foreach ($items as $index => $item) {
            $stmt = $pdo->prepare("INSERT INTO items (checklist_id, section_id, title, is_done, sort_index) VALUES (?, ?, ?, ?, ?)");
            $isDone = $index === 0 ? 1 : 0; // Mark first item as done
            $stmt->execute([$checklist_id, $section_id, $item, $isDone, $index]);
            echo "   ✅ Created item: $item" . ($isDone ? " (completed)" : "") . "\n";
        }
        
        // Re-test the query
        echo "\n📊 Re-testing query with new data...\n";
        $stmt = $pdo->prepare("
            SELECT c.id, c.title, c.description, c.created_at, c.updated_at,
                   COUNT(DISTINCT s.id) as section_count,
                   COUNT(DISTINCT i.id) as item_count,
                   COUNT(DISTINCT CASE WHEN i.is_done = 1 THEN i.id END) as completed_count
            FROM checklists c
            LEFT JOIN sections s ON c.id = s.checklist_id
            LEFT JOIN items i ON c.id = i.checklist_id
            WHERE c.user_id = ? AND c.deleted_at IS NULL
            GROUP BY c.id
            ORDER BY c.updated_at DESC
            LIMIT 20
        ");
        
        $stmt->execute([1]);
        $checklists = $stmt->fetchAll();
        
        echo "   Found " . count($checklists) . " checklist(s)\n";
        if (count($checklists) > 0) {
            $checklist = $checklists[0];
            echo "   Sample: '{$checklist['title']}' - {$checklist['completed_count']}/{$checklist['item_count']} completed\n";
        }
    }
    
    echo "\n🎉 Test completed successfully!\n";
    echo "The API should now work correctly.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📍 File: " . $e->getFile() . "\n";  
    echo "📍 Line: " . $e->getLine() . "\n";
}
?>