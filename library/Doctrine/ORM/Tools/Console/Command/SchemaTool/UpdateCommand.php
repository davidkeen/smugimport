<?php
/*
 *  $Id: UpdateCommand.php 820 2011-01-09 15:05:28Z david $
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ORM\Tools\Console\Command\SchemaTool;

use Symfony\Component\Console\Input\InputArgument,
    Symfony\Component\Console\Input\InputOption,
    Symfony\Component\Console\Input\InputInterface,
    Symfony\Component\Console\Output\OutputInterface,
    Doctrine\ORM\Tools\SchemaTool;

/**
 * Command to update the database schema for a set of classes based on their mappings.
 *
 * @license http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link    www.doctrine-project.org
 * @since   2.0
 * @version $Revision$
 * @author  Benjamin Eberlei <kontakt@beberlei.de>
 * @author  Guilherme Blanco <guilhermeblanco@hotmail.com>
 * @author  Jonathan Wage <jonwage@gmail.com>
 * @author  Roman Borschel <roman@code-factory.org>
 */
class UpdateCommand extends AbstractCommand
{
    /**
     * @see Console\Command\Command
     */
    protected function configure()
    {
        $this
        ->setName('orm:schema-tool:update')
        ->setDescription(
            'Processes the schema and either update the database schema of EntityManager Storage Connection or generate the SQL output.'
        )
        ->setDefinition(array(
            new InputOption(
                'complete', null, InputOption::VALUE_NONE,
                'If defined, all assets of the database which are not relevant to the current metadata will be dropped.'
            ),
            new InputOption(
                'dump-sql', null, InputOption::VALUE_NONE,
                'Instead of try to apply generated SQLs into EntityManager Storage Connection, output them.'
            ),
            new InputOption(
                'force', null, InputOption::VALUE_NONE,
                "Don't ask for the incremental update of the database, but force the operation to run."
            ),
        ))
        ->setHelp(<<<EOT
Processes the schema and either update the database schema of EntityManager Storage Connection or generate the SQL output.
Beware that if --complete is not defined, it will do a save update, which does not delete any tables, sequences or affected foreign keys.
If defined, all assets of the database which are not relevant to the current metadata are dropped by this command.
EOT
        );
    }

    protected function executeSchemaCommand(InputInterface $input, OutputInterface $output, SchemaTool $schemaTool, array $metadatas)
    {
        // Defining if update is complete or not (--complete not defined means $saveMode = true)
        $saveMode = ($input->getOption('complete') !== true);

        if ($input->getOption('dump-sql') === true) {
            $sqls = $schemaTool->getUpdateSchemaSql($metadatas, $saveMode);
            $output->write(implode(';' . PHP_EOL, $sqls) . PHP_EOL);
        } else if ($input->getOption('force') === true) {
            $output->write('Updating database schema...' . PHP_EOL);
            $schemaTool->updateSchema($metadatas, $saveMode);
            $output->write('Database schema updated successfully!' . PHP_EOL);
        } else {
            $output->write('ATTENTION: This operation should not be executed in an production enviroment.' . PHP_EOL);
            $output->write('Use the incremental update to detect changes during development and use' . PHP_EOL);
            $output->write('this SQL DDL to manually update your database in production.' . PHP_EOL . PHP_EOL);

            $sqls = $schemaTool->getUpdateSchemaSql($metadatas, $saveMode);

            if (count($sqls)) {
                $output->write('Schema-Tool would execute ' . count($sqls) . ' queries to update the database.' . PHP_EOL);
                $output->write('Please run the operation with --force to execute these queries or use --dump-sql to see them.' . PHP_EOL);
            } else {
                $output->write('Nothing to update. The database is in sync with the current entity metadata.' . PHP_EOL);
            }
        }
    }
}
