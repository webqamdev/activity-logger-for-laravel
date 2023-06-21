## From v2 to v3

``` bash
composer require webqamdev/activity-logger "^3.0.0"
```

### Database changes

**You can skip this section** if you don't have custom behaviours on database activity logs

- The content of `log_name` column has been moved to `event`
- The `log_name` column now use the value of `activitylogger.log_name` (`activitylogger` by default)
- The `properties` format has change from:
```json
{
  "changed_attribute_1": "new-value",
  "changed_attribute_2": "new-value"
}
```
to
```json
{
  "old": {
    "unchanged_attribute_1": "same-value",
    "changed_attribute_1": "old-value",
    "changed_attribute_2": "old-value"
  },
  "attributes": {
    "unchanged_attribute_1": "same-value",
    "changed_attribute_1": "new-value",
    "changed_attribute_2": "new-value"
  }
}
```

To make your database up to date, you can create a migration:
```php
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up()
    {
        $tableName = config('activitylog.table_name');
        \Illuminate\Support\Facades\DB::table($tableName)
            ->whereNull('event')
            ->eachById(function (\stdClass $row) use ($tableName) {
                \Illuminate\Support\Facades\DB::table($tableName)
                    ->where('id', $row->id)
                    ->update([
                        'log_name' => config('activitylogger.log_name', 'activitylogger'),
                        'event' => $row->log_name,
                        'properties' => json_encode([
                            'attributes' => json_decode($row->properties, true),
                            'old' => [],
                        ]),
                    ]);
            });
    }

    public function down()
    {
    }
};
```

### Config file

- A key `'log_name' => 'activitylogger',` has been added. You can add it, but it's not required

### Models configuration

- The model attribute `$activity_hidden` has been renamed `$logAttributesToIgnore`
