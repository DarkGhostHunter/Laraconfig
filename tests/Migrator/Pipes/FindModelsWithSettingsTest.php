<?php

namespace Tests\Migrator\Pipes;

use DarkGhostHunter\Laraconfig\Migrator\Data;
use DarkGhostHunter\Laraconfig\Migrator\Pipes\FindModelsWithSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Tests\BaseTestCase;


class FindModelsWithSettingsTest extends BaseTestCase
{
    protected Filesystem $filesystem;

    protected function setUp(): void
    {

        parent::setUp();

        $this->filesystem = new Filesystem();

        $this->filesystem->ensureDirectoryExists($this->app->path('Models'));

        $this->filesystem->put($this->app->path('Quz.php'), <<<'CONTENT'
<?php

namespace App;

use DarkGhostHunter\Laraconfig\HasConfig;
use Illuminate\Database\Eloquent\Model;

class Quz extends Model
{
    use HasConfig;
}

CONTENT
        );
        $this->filesystem->put($this->app->path('Qux.php'), <<<'CONTENT'
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Qux extends Model
{
}

CONTENT
        );
        $this->filesystem->put($this->app->path('TraitOfTrait.php'), <<<'CONTENT'
<?php

namespace App;

use DarkGhostHunter\Laraconfig\HasConfig;

trait TraitOfTrait
{
    use HasConfig;
}

CONTENT
        );
        $this->filesystem->put($this->app->path('UsesTraitOfTrait.php'), <<<'CONTENT'
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UsesTraitOfTrait extends Model
{
    use TraitOfTrait;
}

CONTENT
        );
        $this->filesystem->put($this->app->path('AbstractClass.php'), <<<'CONTENT'
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

abstract class AbstractClass extends Model
{
}

CONTENT
        );
        $this->filesystem->put($this->app->path('File.php'), <<<'CONTENT'
<?php

CONTENT
        );

        require_once $this->app->path('Quz.php');
        require_once $this->app->path('Qux.php');
        require_once $this->app->path('TraitOfTrait.php');
        require_once $this->app->path('UsesTraitOfTrait.php');
        require_once $this->app->path('AbstractClass.php');
        require_once $this->app->path('File.php');
    }

    public function test_reads_models_in_root(): void
    {
        if (env('GITHUB_ACTIONS')) {
            self::markTestSkipped('Model find will not work on Github actions.');
        }

        $pipe = new FindModelsWithSettings($this->app, $this->filesystem);
        $data = new Data();

        $result = $pipe->handle($data, fn ($data) => $data);

        static::assertCount(2, $result->models);
        static::assertInstanceOf(\App\Quz::class, $result->models->get(0));
        static::assertInstanceOf(\App\UsesTraitOfTrait::class, $result->models->get(1));
    }

    public function test_reads_models_in_model_dir(): void
    {
        if (env('GITHUB_ACTIONS')) {
            self::markTestSkipped('Model find will not work on Github actions.');
        }

        $this->filesystem->put($this->app->path('Models/Foo.php'), <<<'CONTENT'
<?php

namespace App\Models;

use DarkGhostHunter\Laraconfig\HasConfig;
use Illuminate\Database\Eloquent\Model;

class Foo extends Model
{
    use HasConfig;
}

CONTENT
        );
        $this->filesystem->put($this->app->path('Models/Bar.php'), <<<'CONTENT'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bar extends Model
{
}

CONTENT
        );
        $this->filesystem->put($this->app->path('Models/HasConfig.php'), <<<'CONTENT'
<?php

namespace App;

trait HasConfig
{
}

CONTENT
        );
        $this->filesystem->put($this->app->path('Models/NormalClass.php'), <<<'CONTENT'
<?php

namespace App;

use DarkGhostHunter\Laraconfig\HasConfig;

class NormalClass
{
    use HasConfig;
}

CONTENT
        );
        require_once $this->app->path('Models/Foo.php');
        require_once $this->app->path('Models/Bar.php');
        require_once $this->app->path('Models/HasConfig.php');
        require_once $this->app->path('Models/NormalClass.php');

        $pipe = new FindModelsWithSettings($this->app, $this->filesystem);
        $data = new Data();

        $result = $pipe->handle($data, fn ($data) => $data);

        static::assertCount(1, $result->models);
        static::assertInstanceOf(\App\Models\Foo::class, $result->models->first());
    }

    protected function tearDown(): void
    {
        $this->filesystem->delete('Models/HasConfig.php');
        $this->filesystem->delete('AbstractClass.php');
        $this->filesystem->delete('TraitOfTrait.php');
        $this->filesystem->delete('UsesTraitOfTrait.php');
        $this->filesystem->delete('Quz.php');
        $this->filesystem->delete('Qux.php');
        $this->filesystem->delete('Models/Foo.php');
        $this->filesystem->delete('Models/Bar.php');

        $this->filesystem->deleteDirectory($this->app->path('Models'));

        parent::tearDown();
    }
}
