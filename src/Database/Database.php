<?php

namespace Utopia\Database;

use Exception;
use Utopia\Database\Validator\Authorization;
use Utopia\Database\Validator\Structure;
use Utopia\Database\Exception\Authorization as AuthorizationException;
use Utopia\Database\Exception\Structure as StructureException;

class Database
{
    const METADATA = 'metadata';

    // Simple Types
    const VAR_STRING = 'string';
    const VAR_INTEGER = 'integer';
    const VAR_FLOAT = 'float';
    const VAR_BOOLEAN = 'boolean';
    
    // Relationships Types
    const VAR_DOCUMENT = 'document';
    
    // Index Types
    const INDEX_KEY = 'text';
    const INDEX_FULLTEXT = 'fulltext';
    const INDEX_UNIQUE = 'unique';
    const INDEX_SPATIAL = 'spatial';

    // Orders
    const ORDER_ASC = 'ASC';
    const ORDER_DESC = 'DESC';

    // Permissions
    const PERMISSION_READ = 'read';
    const PERMISSION_WRITE = 'write';

    // Collections
    const COLLECTIONS = 'collections';

    /**
     * @var Adapter
     */
    protected $adapter;

    /**
     * Parent Collection
     * Defines the structure for both system and custom collections
     */
    protected $collection = [
        '$id' => 'collections',
        'name' => 'collections',
        'attributes' => [
            [
                '$id' => 'type',
                'type' => self::VAR_STRING,
                'size' => 64,
                'signed' => true,
                'array' => false,
                'filters' => [],
            ],
            [
                '$id' => 'size',
                'type' => self::VAR_INTEGER,
                'size' => 0,
                'signed' => true,
                'array' => false,
                'filters' => [],
            ],
            [
                '$id' => 'signed',
                'type' => self::VAR_BOOLEAN,
                'size' => 0,
                'signed' => true,
                'array' => false,
                'filters' => [],
            ],
            [
                '$id' => 'array',
                'type' => self::VAR_BOOLEAN,
                'size' => 0,
                'signed' => true,
                'array' => false,
                'filters' => [],
            ]
        ],
        'indexes' => [],
    ];

    /**
     * @var array
     */
    static public $filters = [];

    /**
     * @param Adapter $adapter
     */
    public function __construct(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * Set Namespace.
     *
     * Set namespace to divide different scope of data sets
     *
     * @param string $namespace
     *
     * @return $this
     *
     * @throws Exception
     */
    public function setNamespace(string $namespace): self
    {
        $this->adapter->setNamespace($namespace);

        return $this;
    }

    /**
     * Get Namespace.
     *
     * Get namespace of current set scope
     *
     * @return string
     *
     * @throws Exception
     */
    public function getNamespace(): string
    {
        return $this->adapter->getNamespace();
    }

    /**
     * Create Database
     *
     * @return bool
     */
    public function create(): bool
    {
        $this->adapter->create();

        $this->createCollection(self::COLLECTIONS);
        $this->createAttribute(self::COLLECTIONS, 'name', self::VAR_STRING, 128);
        $this->createAttribute(self::COLLECTIONS, 'attributes', self::VAR_STRING, 8064);
        $this->createAttribute(self::COLLECTIONS, 'indexes', self::VAR_STRING, 8064);
        $this->createIndex(self::COLLECTIONS, '_key_1', self::INDEX_UNIQUE, ['name']);

        return true;
    }

    /**
     * List Databases
     *
     * @return array
     */
    public function list(): array
    {
        return $this->adapter->list();
    }

    /**
     * Delete Database
     *
     * @return bool
     */
    public function delete(): bool
    {
        return $this->adapter->delete();
    }

    /**
     * Create Collection
     * 
     * @param string $id
     * 
     * @return Document
     */
    public function createCollection(string $id): Document
    {
        $this->adapter->createCollection($id);

        if($id === self::COLLECTIONS) {
            return new Document($this->collection);
        }

        return $this->createDocument(Database::COLLECTIONS, new Document([
            '$id' => $id,
            'name' => $id,
            'attributes' => [],
            'indexes' => [],
        ]));
    }

    /**
     * Get Collection
     * 
     * @param string $collection
     * @param string $id
     *
     * @return Document
     */
    public function getCollection(string $id): Document
    {
        return $this->getDocument(self::COLLECTIONS, $id);
    }

    /**
     * List Collections
     * 
     * @return array
     */
    public function listCollections(): array
    {
        return $this->adapter->listCollections();
    }

    /**
     * Delete Collection
     * 
     * @param string $id
     * 
     * @return bool
     */
    public function deleteCollection(string $id): bool
    {
        // TODO Delete collection document first

        return $this->adapter->deleteCollection($id);
    }

    /**
     * Create Attribute
     * 
     * @param string $collection
     * @param string $id
     * @param string $type
     * @param int $size
     * @param bool $array
     * 
     * @return bool
     */
    public function createAttribute(string $collection, string $id, string $type, int $size, bool $signed = true, bool $array = false, array $filters = []): bool
    {
        $collection = $this->getCollection($collection);

        $collection->setAttribute('attributes', new Document([
            '$id' => $id,
            'type' => $type,
            'size' => $size,
            'signed' => $signed,
            'array' => $array,
            'filters' => $filters,
        ]), Document::SET_TYPE_APPEND);

        //$this->updateDocument();

        switch ($type) {
            case self::VAR_STRING:
                if($size > $this->adapter->getStringLimit()) {
                    throw new Exception('Max size allowed for string is: '.number_format($this->adapter->getStringLimit()));
                }
                break;

            case self::VAR_INTEGER:
                $limit = ($signed) ? $this->adapter->getIntLimit() / 2 : $this->adapter->getIntLimit();
                if($size > $limit) {
                    throw new Exception('Max size allowed for int is: '.number_format($limit));
                }
                break;
            case self::VAR_FLOAT:
            case self::VAR_BOOLEAN:
                break;
            default:
                throw new Exception('Unknown attribute type: '.$type);
                break;
        }

        return $this->adapter->createAttribute($collection->getId(), $id, $type, $size, $signed, $array);
    }

    /**
     * Delete Attribute
     * 
     * @param string $collection
     * @param string $id
     * 
     * @return bool
     */
    public function deleteAttribute(string $collection, string $id): bool
    {
        return $this->adapter->deleteAttribute($collection, $id);
    }

    /**
     * Create Index
     *
     * @param string $collection
     * @param string $id
     * @param string $type
     * @param array $attributes
     * @param array $lengths
     * @param array $orders
     *
     * @return bool
     */
    public function createIndex(string $collection, string $id, string $type, array $attributes, array $lengths = [], array $orders = []): bool
    {
        if(empty($attributes)) {
            throw new Exception('Missing attributes');
        }

        switch ($type) {
            case self::INDEX_KEY:
                if(!$this->adapter->getSupportForIndex()) {
                    throw new Exception('Key index is not supported');
                }
                break;

            case self::INDEX_UNIQUE:
                if(!$this->adapter->getSupportForUniqueIndex()) {
                    throw new Exception('Unique index is not supported');
                }
                break;

            case self::INDEX_FULLTEXT:
                if(!$this->adapter->getSupportForUniqueIndex()) {
                    throw new Exception('Fulltext index is not supported');
                }
                break;
            
            default:
                throw new Exception('Unknown index type: '.$type);
                break;
        }

        return $this->adapter->createIndex($collection, $id, $type, $attributes, $lengths, $orders);
    }

    /**
     * Delete Index
     *
     * @param string $collection
     * @param string $id
     *
     * @return bool
     */
    public function deleteIndex(string $collection, string $id): bool
    {
        return $this->adapter->deleteIndex($collection, $id);
    }

    /**
     * Get Document
     * 
     * @param string $collection
     * @param string $id
     *
     * @return Document
     */
    public function getDocument(string $collection, string $id): Document
    {
        if($collection === self::COLLECTIONS && $id === self::COLLECTIONS) {
            return new Document($this->collection);
        }

        $collection = $this->getDocument(self::COLLECTIONS, $collection);
        $document   = $this->adapter->getDocument($collection->getId(), $id);

        // $validator = new Authorization($document, self::PERMISSION_READ);

        // if (!$validator->isValid($document->getPermissions())) { // Check if user has read access to this document
        //     return new Document();
        // }

        // $document = $this->decode($document);

        return $document;
    }

    /**
     * Create Document
     * 
     * @param string $collection
     * @param Document $data
     *
     * @return Document
     *
     * @throws AuthorizationException
     * @throws StructureException
     */
    public function createDocument(string $collection, Document $document): Document
    {
        // $validator = new Authorization($document, self::PERMISSION_WRITE);

        // if (!$validator->isValid($document->getPermissions())) { // Check if user has write access to this document
        //     throw new AuthorizationException($validator->getDescription());
        // }

        // $document = $this->encode($document);
        // $validator = new Structure($this);

        // if (!$validator->isValid($document)) {
        //     throw new StructureException($validator->getDescription()); // var_dump($validator->getDescription()); return false;
        // }

        $document
            ->setAttribute('$id', empty($document->getId()) ? $this->getId(): $document->getId())
        ;
        
        $document = $this->adapter->createDocument($collection, $document);
        
        // $document = $this->decode($document);

        return $document;
    }

    /**
     * Update Document
     * 
     * @param array $collection
     * @param array $id
     * @param array $data
     *
     * @return Document|false
     *
     * @throws Exception
     */
    public function updateDocument(string $collection, string $id, array $data): Document
    {
        if (!isset($data['$id'])) {
            throw new Exception('Must define $id attribute');
        }

        $document = $this->getDocument($collection, $id); // TODO make sure user don\'t need read permission for write operations

        // Make sure reserved keys stay constant
        $data['$id'] = $document->getId();
        $data['$collection'] = $document->getCollection();

        // $validator = new Authorization($document, 'write');

        // if (!$validator->isValid($document->getPermissions())) { // Check if user has write access to this document
        //     throw new AuthorizationException($validator->getDescription()); // var_dump($validator->getDescription()); return false;
        // }

        $new = new Document($data);

        // if (!$validator->isValid($new->getPermissions())) { // Check if user has write access to this document
        //     throw new AuthorizationException($validator->getDescription()); // var_dump($validator->getDescription()); return false;
        // }

        // $new = $this->encode($new);

        // $validator = new Structure($this);

        // if (!$validator->isValid($new)) { // Make sure updated structure still apply collection rules (if any)
        //     throw new StructureException($validator->getDescription()); // var_dump($validator->getDescription()); return false;
        // }

        // $new = new Document($this->adapter->updateDocument($collection, $new->getArrayCopy()));
        
        // $new = $this->decode($new);

        return $new;
    }

    /**
     * @param string $collection
     * @param string $id
     *
     * @return Document|false
     *
     * @throws AuthorizationException
     */
    public function deleteDocument(string $collection, string $id): bool
    {
        // $document = $this->getDocument($collection, $id);

        // $validator = new Authorization($document, 'write');

        // if (!$validator->isValid($document->getPermissions())) { // Check if user has write access to this document
        //     throw new AuthorizationException($validator->getDescription());
        // }

        // return new Document($this->adapter->deleteDocument($collection, $id));

        return false;
    }

    // /**
    //  * @param string $collection
    //  * @param array $options
    //  *
    //  * @return Document[]
    //  */
    // public function find(string $collection, array $options)
    // {
    //     $options = \array_merge([
    //         'offset' => 0,
    //         'limit' => 15,
    //         'search' => '',
    //         'relations' => true,
    //         'orderField' => '',
    //         'orderType' => 'ASC',
    //         'orderCast' => 'int',
    //         'filters' => [],
    //     ], $options);

    //     $results = $this->adapter->find($this->getDocument(self::COLLECTIONS, $collection), $options);

    //     foreach ($results as &$node) {
    //         $node = $this->decode(new Document($node));
    //     }

    //     return $results;
    // }

    // /**
    //  * @param string $collection
    //  * @param array $options
    //  *
    //  * @return Document
    //  */
    // public function findFirst(string $collection, array $options)
    // {
    //     $results = $this->find($collection, $options);
    //     return \reset($results);
    // }

    // /**
    //  * @param string $collection
    //  * @param array $options
    //  *
    //  * @return Document
    //  */
    // public function findLast(string $collection, array $options)
    // {
    //     $results = $this->find($collection, $options);
    //     return \end($results);
    // }

    // /**
    //  * @param array $options
    //  *
    //  * @return int
    //  */
    // public function count(array $options)
    // {
    //     $options = \array_merge([
    //         'filters' => [],
    //     ], $options);

    //     $results = $this->adapter->count($options);

    //     return $results;
    // }

    // /**
    //  * @param array $data
    //  *
    //  * @return Document|false
    //  *
    //  * @throws Exception
    //  */
    // public function overwriteDocument(array $data)
    // {
    //     if (!isset($data['$id'])) {
    //         throw new Exception('Must define $id attribute');
    //     }

    //     $document = $this->getDocument($data['$collection'], $data['$id']); // TODO make sure user don\'t need read permission for write operations

    //     $validator = new Authorization($document, 'write');

    //     if (!$validator->isValid($document->getPermissions())) { // Check if user has write access to this document
    //         throw new AuthorizationException($validator->getDescription()); // var_dump($validator->getDescription()); return false;
    //     }

    //     $new = new Document($data);

    //     if (!$validator->isValid($new->getPermissions())) { // Check if user has write access to this document
    //         throw new AuthorizationException($validator->getDescription()); // var_dump($validator->getDescription()); return false;
    //     }

    //     $new = $this->encode($new);

    //     $validator = new Structure($this);

    //     if (!$validator->isValid($new)) { // Make sure updated structure still apply collection rules (if any)
    //         throw new StructureException($validator->getDescription()); // var_dump($validator->getDescription()); return false;
    //     }

    //     $new = new Document($this->adapter->updateDocument($this->getDocument(self::COLLECTIONS, $new->getCollection()), $new->getId(), $new->getArrayCopy()));

    //     $new = $this->decode($new);

    //     return $new;
    // }

    // /**
    //  * @return array
    //  */
    // public function getDebug()
    // {
    //     return $this->adapter->getDebug();
    // }

    // /**
    //  * @return int
    //  */
    // public function getSum()
    // {
    //     $debug = $this->getDebug();

    //     return (isset($debug['sum'])) ? $debug['sum'] : 0;
    // }

    // /**
    //  * Add Attribute Filter
    //  *
    //  * @param string $name
    //  * @param callable $encode
    //  * @param callable $decode
    //  *
    //  * @return void
    //  */
    // static public function addFilter(string $name, callable $encode, callable $decode): void
    // {
    //     self::$filters[$name] = [
    //         'encode' => $encode,
    //         'decode' => $decode,
    //     ];
    // }

    // public function encode(Document $document):Document
    // {
    //     if($document->getCollection() === null) {
    //         return $document;
    //     }

    //     $collection = $this->getDocument(self::COLLECTIONS, $document->getCollection(), true , false);
    //     $rules = $collection->getAttribute('rules', []);

    //     foreach ($rules as $key => $rule) {
    //         $key = $rule->getAttribute('key', null);
    //         $type = $rule->getAttribute('type', null);
    //         $array = $rule->getAttribute('array', false);
    //         $filters = $rule->getAttribute('filter', []);
    //         $value = $document->getAttribute($key, null);

    //         if (($value !== null)) {
    //             if ($type === self::VAR_DOCUMENT) {
    //                 if($array) {
    //                     $list = [];
    //                     foreach ($value as $child) {
    //                         $list[] = $this->encode($child);
    //                     }

    //                     $document->setAttribute($key, $list);
    //                 } else {
    //                     $document->setAttribute($key, $this->encode($value));
    //                 }
    //             } else {
    //                 foreach ($filters as $filter) {
    //                     $value = $this->encodeAttribute($filter, $value);
    //                     $document->setAttribute($key, $value);
    //                 }
    //             }
    //         }
    //     }

    //     return $document;
    // }

    // public function decode(Document $document):Document
    // {
    //     if($document->getCollection() === null) {
    //         return $document;
    //     }

    //     $collection = $this->getDocument(self::COLLECTIONS, $document->getCollection(), true , false);
    //     $rules = $collection->getAttribute('rules', []);

    //     foreach ($rules as $key => $rule) {
    //         $key = $rule->getAttribute('key', null);
    //         $type = $rule->getAttribute('type', null);
    //         $array = $rule->getAttribute('array', false);
    //         $filters = $rule->getAttribute('filter', []);
    //         $value = $document->getAttribute($key, null);

    //         if (($value !== null)) {
    //             if ($type === self::VAR_DOCUMENT) {
    //                 if($array) {
    //                     $list = [];
    //                     foreach ($value as $child) {
    //                         $list[] = $this->decode($child);
    //                     }

    //                     $document->setAttribute($key, $list);
    //                 } else {
    //                     $document->setAttribute($key, $this->decode($value));
    //                 }
    //             } else {
    //                 foreach (array_reverse($filters) as $filter) {
    //                     $value = $this->decodeAttribute($filter, $value);
    //                     $document->setAttribute($key, $value);
    //                 }
    //             }
    //         }
    //     }

    //     return $document;
    // }

    // /**
    //  * Encode Attribute
    //  * 
    //  * @param string $name
    //  * @param mixed $value
    //  */
    // static protected function encodeAttribute(string $name, $value)
    // {
    //     if (!isset(self::$filters[$name])) {
    //         return $value;
    //         throw new Exception('Filter not found');
    //     }

    //     try {
    //         $value = self::$filters[$name]['encode']($value);
    //     } catch (\Throwable $th) {
    //         $value = null;
    //     }

    //     return $value;
    // }

    // /**
    //  * Decode Attribute
    //  * 
    //  * @param string $name
    //  * @param mixed $value
    //  */
    // static protected function decodeAttribute(string $name, $value)
    // {
    //     if (!isset(self::$filters[$name])) {
    //         return $value;
    //         throw new Exception('Filter not found');
    //     }

    //     try {
    //         $value = self::$filters[$name]['decode']($value);
    //     } catch (\Throwable $th) {
    //         $value = null;
    //     }

    //     return $value;
    // }

    /**
     * Get 13 Chars Unique ID.
     * 
     * @return string
     */
    public function getId(): string
    {
        return \uniqid();
    }
}
