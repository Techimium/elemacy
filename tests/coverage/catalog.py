#!/usr/bin/env python3
"""Read shipped declarations without booting WordPress or using development data.

This is a declaration inventory, not proof that features work. Unsupported
identifier expressions fail explicitly instead of silently losing coverage.
"""

import argparse
import json
from pathlib import Path
import re
import xml.etree.ElementTree as ET


FREE = Path(__file__).resolve().parents[2]
PRO = FREE.parent / 'elemacy-pro'


def php(path):
    text = path.read_text()
    return re.sub(r'/\*.*?\*/|//[^\n]*', '', text, flags=re.S)


def class_file(root, classname):
    namespace = 'ElemacyPro' if root.name == 'elemacy-pro' else 'Elemacy'
    if not classname.startswith(namespace + '\\'):
        raise ValueError('Unexpected class namespace: ' + classname)
    return root / 'src' / (classname[len(namespace) + 1:].replace('\\', '/') + '.php')


def configured_classes(root, relative):
    text = php(root / relative)
    aliases = {}
    for name, alias in re.findall(r'use\s+([\w\\]+)(?:\s+as\s+(\w+))?\s*;', text):
        aliases[alias or name.split('\\')[-1]] = name
    result = []
    for alias in re.findall(r'\b(\w+)::(?:class|make_all\s*\()', text):
        if alias not in aliases:
            raise ValueError(f'Unresolved class {alias} in {relative}')
        if aliases[alias] not in result:
            result.append(aliases[alias])
    return result


def identifier(path, method='get_name'):
    text = php(path)
    match = re.search(r'function\s+' + method + r'\s*\([^)]*\)[^{]*\{\s*return\s+([^;]+);', text)
    if not match:
        raise ValueError(f'Cannot read {method} in {path}')
    expression = match[1].strip()
    literal = re.fullmatch(r"'([^']+)'", expression)
    if literal:
        return literal[1]
    taxonomy = re.fullmatch(r"'([^']+)'\s*\.\s*\$this->taxonomy", expression)
    if taxonomy:
        return taxonomy[1] + '{taxonomy}'
    raise ValueError(f'Unsupported identifier expression in {path}: {expression}')


def feature(root, kind, name, source, implementation='real'):
    owner = root.name
    required = []
    if owner == 'elemacy-pro':
        required.append('elemacy-pro')
    if 'Acf' in source or 'acf-' in name:
        required.append('acf-pro' if owner == 'elemacy-pro' else 'acf')
    if 'WooCommerce' in source:
        required.append('woocommerce')
    return {
        'id': owner + ':' + kind + ':' + name,
        'owner': owner, 'kind': kind, 'identifier': name,
        'source': source, 'implementation': implementation,
        'prerequisites': required,
    }


def declarations(root):
    result = []
    configs = [('module', 'src/Config/modules.php')]
    if root == FREE:
        configs += [
            ('widget', 'src/Modules/Widgets/Config/widgets.php'),
            ('tag', 'src/Modules/DynamicTags/Config/tags.php'),
            ('condition', 'src/Config/conditions.php'),
            ('trigger', 'src/Modules/Popups/Config/triggers.php'),
            ('rule', 'src/Modules/Popups/Config/rules.php'),
        ]
    else:
        configs += [
            ('condition', 'src/Extensions/Conditions/Config/conditions.php'),
            ('trigger', 'src/Extensions/Popups/Config/triggers.php'),
            ('rule', 'src/Extensions/Popups/Config/rules.php'),
        ]
    for kind, config in configs:
        for classname in configured_classes(root, config):
            path = class_file(root, classname)
            result.append(feature(root, kind, identifier(path), str(path.relative_to(root)),
                                  'placeholder' if '\\Mock\\' in classname else 'real'))
    if root == FREE:
        for config, field in [
            ('src/Modules/ThemeBuilder/ThemeBuilder.php', 'types'),
        ]:
            text = php(root / config)
            array = re.search(r'\$' + field + r'\s*=\s*\[(.*?)\];', text, re.S)
            if not array:
                raise ValueError('Cannot read template types from ' + config)
            for name in re.findall(r"'([^']+)'\s*=>", array[1]):
                result.append(feature(root, 'template', name, config))
        for path in ['src/TemplateLibrary/LibraryBootstrap.php', 'src/Modules/Widgets/Widgets.php']:
            for name in re.findall(r"new TypeDefinition\(\s*'([^']+)'", php(root / path)):
                result.append(feature(root, 'template', name, path))
        types = 'src/Modules/Popups/Support/PopupTypes.php'
        for name in re.findall(r"public const \w+\s*=\s*'([^']+)'", php(root / types)):
            result.append(feature(root, 'popup-type', name, types))
        for kind, config in [('form-field', 'src/Config/form_field_types.php'),
                             ('form-action', 'src/Config/form_actions.php')]:
            for classname in configured_classes(root, config):
                path = class_file(root, classname)
                result.append(feature(root, kind, identifier(path, 'key'), str(path.relative_to(root))))
        providers = [('src/Modules/Widgets/Widgets.php', 'src/Modules/Widgets/DataSources')]
    else:
        providers = [('src/Extensions/LoopDataSources/ProLoopDataSources.php',
                      'src/Extensions/LoopDataSources')]
    for provider, directory in providers:
        for classname in re.findall(r'->register\(new\s+(\w+DataSource)\(', php(root / provider)):
            path = root / directory / (classname + '.php')
            result.append(feature(root, 'loop-source', identifier(path, 'get_key'), str(path.relative_to(root))))
    if len({entry['id'] for entry in result}) != len(result):
        raise ValueError('Duplicate declared feature IDs in ' + root.name)
    return sorted(result, key=lambda entry: entry['id'])


def runners(root):
    result = []
    patterns = [('static-browser', 'tests/e2e/run-popups*.mjs'),
                ('static-browser', 'tests/e2e/run-elementor-lifecycle.mjs'),
                ('native-browser', 'tests/e2e/run-*.mjs'),
                ('logic', 'tests/php/run.php'),
                ('architecture', 'tests/architecture/check.php'),
                ('release', 'tests/release/*test.py'),
                ('release', 'src-react/scripts/verify-*.py'),
                ('performance', 'tests/performance/*.php')]
    seen = set()
    for layer, pattern in patterns:
        for path in sorted(root.glob(pattern)):
            relative = str(path.relative_to(root))
            if relative in seen:
                continue
            seen.add(relative)
            result.append({'id': root.name + ':runner:' + path.stem,
                           'layer': layer, 'path': relative})
    config = root / 'tests/integration/phpunit.xml.dist'
    if config.exists():
        for node in ET.parse(config).findall('.//testsuite/file'):
            path = 'tests/integration/' + node.text
            if not (root / path).is_file():
                raise ValueError('Missing registered PHPUnit scenario: ' + path)
            result.append({'id': root.name + ':integration:' + Path(path).stem,
                           'layer': 'native-integration', 'path': path})
    for path in sorted(root.glob('tests/integration/fixtures/uninstall-*.php')):
        result.append({'id': root.name + ':lifecycle:' + path.stem,
                       'layer': 'native-integration', 'path': str(path.relative_to(root))})
    return result


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--owner', choices=['elemacy', 'elemacy-pro', 'both'], default='both')
    args = parser.parse_args()
    roots = [FREE, PRO] if args.owner == 'both' else [FREE if args.owner == 'elemacy' else PRO]
    print(json.dumps({'version': 1, 'plugins': {
        root.name: {'features': declarations(root), 'runners': runners(root)} for root in roots
    }}, indent=2))


if __name__ == '__main__':
    main()
