# Configurare

[![Build Status](https://travis-ci.org/minond/Configurare.png?branch=master)](https://travis-ci.org/minond/Configurare)
[![Coverage Status](https://coveralls.io/repos/minond/Configurare/badge.png?branch=master)](https://coveralls.io/r/minond/Configurare?branch=master)
[![Latest Stable Version](https://poser.pugx.org/minond/configurare/v/stable.png)](https://packagist.org/packages/minond/configurare)
[![Dependencies Status](https://depending.in/minond/Configurare.png)](http://depending.in/minond/Configurare)
[![Scrutinizer Quality Score](https://scrutinizer-ci.com/g/minond/Configurare/badges/quality-score.png?s=6fe5f88fec0115f3fcee8bf3bc24bd269e457b9c)](https://scrutinizer-ci.com/g/minond/Configurare/)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/3ed4e4eb-0c16-46ea-a29f-b1ca2041f15c/mini.png)](https://insight.sensiolabs.com/projects/3ed4e4eb-0c16-46ea-a29f-b1ca2041f15c)

## Sample usage
#### Initializing configuration
```php
require 'vendor/autoload.php';

use Efficio\Configurare\Configuration;

$conf = new Configuration;
$conf->setDirectory('./config/');
$conf->setFormat(Configuration::YAML);
```

```yaml
# config/app.yml
name: 'My Application'
usa:
  utah:
    provo:
      author: 'Marcos Minond'
```

#### Getting values
```php
// looks for in ./config/app.yml
// this gets [ 'name': ]
echo $conf->get('app:name'); // => My Application

// this gets [ 'usa': 'utah': 'provo': 'author': ]
echo $conf->get('app:usa:utah:provo:author'); // => Marcos Minond
```

#### Setting values
```php
// if a key(s) already exists, just set it
$conf->set('app:name', 'My Other Application');

// if they do not then the write must be forced by passing a third parameter
// set to true
$conf->set('app:does:not:exists:yet', 'yes', true);
```

```yaml
# config/app.yml
name: 'My Other Application'
usa:
  utah:
    provo:
      author: 'Marcos Minond'
does:
  not:
    exists:
      yet: 'yes'
```

#### Enviroments
Adding enviroments allows additional configuration files, which may or may not
be tracked by version control, to be used. For example, you may commit a
"default" config/app.yml configuration file which makes assumptions about the
enviroments (ie. database connection information) and overwrite it using
config/app.prod.yml. This "prod" file has data which is sensative and is only
stored in the production server where your application is running. This allows
you to use the same configuration retrieval code, get the correct configuration
for your enviroments, AND not have to check that into source control.

```php
// I can have one enviroment or multiple
$conf->setEnvironments([ 'dev', 'test' ]);

// the following files will be parsed and merged before the configuration
// value is sent back
// - config/database.yaml
// - config/database.dev.yaml
// - config/database.test.yaml
$conf->get('database:connection:username');
```

#### Caching
Configurare is compatible with the [Cache](https://github.com/minond/Cache) package
```php
use Efficio\Cache\NullCache;
$conf->setCache(new NullCache);
```

