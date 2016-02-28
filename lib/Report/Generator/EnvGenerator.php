<?php

/*
 * This file is part of the PHPBench package
 *
 * (c) Daniel Leech <daniel@dantleech.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpBench\Report\Generator;

use PhpBench\Console\OutputAwareInterface;
use PhpBench\Dom\Document;
use PhpBench\Model\SuiteCollection;
use PhpBench\Registry\Config;
use PhpBench\Report\Generator\Table\Row;
use PhpBench\Report\GeneratorInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Report generator for environmental information.
 *
 * NOTE: The Table report generator could probably be improved to be able to incorporate
 *       this report somehow.
 */
class EnvGenerator implements GeneratorInterface, OutputAwareInterface
{
    private $output;

    /**
     * {@inheritdoc}
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultConfig()
    {
        return array(
            'title' => null,
            'description' => null,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getSchema()
    {
        return array(
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => array(
                'title' => array(
                    'type' => array('string', 'null'),
                ),
                'description' => array(
                    'type' => array('string', 'null'),
                ),
            ),
        );
    }

    /**
     * {@inheritdoc}
     */
    public function generate(SuiteCollection $suiteCollection, Config $config)
    {
        $document = new Document();
        $reportsEl = $document->createRoot('reports');
        $reportsEl->setAttribute('name', 'table');
        $reportEl = $reportsEl->appendElement('report');

        if (isset($config['title'])) {
            $reportEl->setAttribute('title', $config['title']);
        }

        if (isset($config['description'])) {
            $reportEl->appendElement('description', $config['description']);
        }

        foreach ($suiteCollection as $suite) {
            $tableEl = $reportEl->appendElement('table');
            $tableEl->setAttribute('title', sprintf(
                'Suite #%s %s', $suite->getIdentifier(), $suite->getDate()->format('Y-m-d H:i:s')
            ));

            $groupEl = $tableEl->appendElement('group');
            $groupEl->setAttribute('name', 'body');

            foreach ($suite->getEnvInformations() as $envInformation) {
                foreach ($envInformation as $key => $value) {
                    $rowEl = $groupEl->appendElement('row');

                    $cellEl = $rowEl->appendElement('cell', $envInformation->getName());
                    $cellEl->setAttribute('name', 'provider');
                    $cellEl = $rowEl->appendElement('cell', $key);
                    $cellEl->setAttribute('name', 'key');
                    $cellEl = $rowEl->appendElement('cell', $value);
                    $cellEl->setAttribute('name', 'value');
                }
            }
        }

        return $document;
    }

    private function getClassShortName($fullName)
    {
        $parts = explode('\\', $fullName);
        end($parts);

        return current($parts);
    }

    private function resolveCompareColumnName(Row $row, $name, $index = 1)
    {
        if (!isset($row[$name])) {
            return $name;
        }

        $newName = $name . '#' . (string) $index++;

        if (!isset($row[$newName])) {
            return $newName;
        }

        return $this->resolveCompareColumnName($row, $name, $index);
    }
}
