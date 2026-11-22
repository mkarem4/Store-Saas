<?php

return [
    'column_manager' => [
        'position' => 'الموضع :position من :count.',
        'position_in_group' => 'الموضع :position من :count في :group.',
        'reordered' => 'تم نقل :column إلى الموضع :position من :count.',
        'reordered_deferred' => 'تم نقل :column إلى الموضع :position من :count. طبّق الأعمدة لحفظ التغيير.',
        'reordered_in_group' => 'تم نقل :column إلى الموضع :position من :count في :group.',
        'reordered_in_group_deferred' => 'تم نقل :column إلى الموضع :position من :count في :group. طبّق الأعمدة لحفظ التغيير.',
        'actions' => [
            'move_up' => ['label' => 'تحريك :column لأعلى'],
            'move_down' => ['label' => 'تحريك :column لأسفل'],
        ],
    ],
    'columns' => ['editable' => [
        'saving' => 'جارٍ حفظ التغيير…',
        'saved' => 'تم حفظ التغيير.',
        'failure' => 'تعذر تأكيد الحفظ. حدّث الصفحة قبل المحاولة مرة أخرى.',
        'unavailable' => 'لم يعد بالإمكان تعديل هذا العنصر.',
    ]],
    'actions' => [
        'move_record_up' => ['label' => 'تحريك :label لأعلى'],
        'move_record_down' => ['label' => 'تحريك :label لأسفل'],
    ],
    'reordering' => [
        'position' => ':label، الموضع :position من :count.',
        'moved' => 'تم نقل :label إلى الموضع :position من :count.',
    ],
];
