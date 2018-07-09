<?php

namespace Eav;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

class Entity extends Model
{
    protected static $baseEntity = [];
    protected static $entityIdCache = [];
    
    protected $fillable = [
        'entity_code', 'entity_name', 'entity_class', 'entity_table',
        'default_attribute_set_id', 'additional_attribute_table',
        'relation_entity_ids', 'is_flat_enabled', 'entity_desc'
    ];
    
    public $timestamps = false;

    public static $rules = [
        'entity_code' => 'required|unique:entities',
        'entity_name' => 'required|unique:entities',
        'entity_class' => 'required|unique:entities',
        'entity_table' => 'required|unique:entities',
    ];
    
    public function canUseFlat()
    {
        return $this->getAttribute('is_flat_enabled');
    }
    
    public function getEntityTablePrefix()
    {
        $tableName = Str::singular($this->getAttribute('entity_table'));
        $tablePrefix = $this->getConnection()->getTablePrefix();
        if ($tablePrefix != '') {
            $tableName = "$tablePrefix.$tableName";
        }
        return $tableName;
    }

    public function setRelationEntityIdsAttribute($value)
    {
        if (is_array($value)){
            $this->attributes['relation_entity_ids'] = json_encode($value);
        }
    }

    public function getRelationEntityIdsAttribute()
    {
        return ($ids = $this->attributes['relation_entity_ids']) ? json_decode($ids) : $ids;
    }
        
    public function attributeSet()
    {
        return $this->hasMany(AttributeSet::class, 'entity_id');
    }

    public function attributes()
    {
        return $this->hasManyThrough(Attribute::class, EntityAttribute::class, 'entity_id', 'id');
    }

    public function attributes_form()//todo debug for hasManyThrough in form
    {
        return $this->hasMany(Attribute::class,'entity_id');
    }

    public function object_relation()
    {
        return $this->hasManyThrough(static::class, EntityRelation::class,'entity_id','id','id','relation_entity_id');//
    }

    public function entity_relations()
    {
        return $this->hasMany(EntityRelation::class, 'entity_id')
            ->whereIn('relation_entity_id',$this->getRelationEntityIdsAttribute())
            ->with(['entity','relation']);
    }
    
    public static function findByCode($code)
    {
        if (!isset(static::$entityIdCache[$code])) {
            $entity= static::where('entity_code', '=', $code)->firstOrFail();
                                            
            static::$entityIdCache[$entity->getAttribute('entity_code')] = $entity->getKey();
            
            static::$baseEntity[$entity->getKey()] = $entity;
        }
                    
        return static::$baseEntity[static::$entityIdCache[$code]];
    }
    
    public static function findById($id)
    {
        if (!isset(static::$baseEntity[$id])) {
            $entity = static::findOrFail($id);
            
            static::$entityIdCache[$entity->getAttribute('entity_code')] = $entity->getKey();
            
            static::$baseEntity[$id] = $entity;
        }
                    
        return static::$baseEntity[$id];
    }
    
    public function defaultAttributeSet()
    {
        return $this->hasOne(AttributeSet::class, 'entity_id');
    }
    
    public function describe()
    {
        $table = $this->getAttribute('entity_table');
        
        $connection = \DB::connection();
        
        $database = $connection->getDatabaseName();

        $table = $connection->getTablePrefix().$table;
        
        $result = \DB::table('information_schema.columns')
                ->where('table_schema', $database)
                ->where('table_name', $table)
                ->get();
                
        return new Collection(json_decode(json_encode($result), true));
    }
}
