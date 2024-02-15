<?php
// Variables
$githubKey = $_SERVER["GITHUB_KEY"] ?? false;
if (!$githubKey) {
    echo "The GITHUB_KEY environment variable is not defined.";
    $githubKey = "XXX";
}
$repoURL = "https://$githubKey@github.com/ginifizz/frankenphp.git";

// Constants
const DOCS_TO_CLONE = "docs/fr";
const DESTINATION_DIRECTORY = __DIR__ . "/content/fr";
const DOCS_DESTINATION = DESTINATION_DIRECTORY . "/docs";
const NAV_DESTINATION = __DIR__ . "/data/fr/nav.yaml";
const TEMP_DIR = __DIR__ . "/temp-documentation-fr";

// Regex for performing transformations
const LINKS_REGEX = '/\[([^\]]+)\]\(([^)]+)\)/';

// Function to transform the navigation from markdown to yaml
function markdownToYaml($markdownText)
{
    $links = [];
    preg_match_all(LINKS_REGEX, $markdownText, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $title = str_replace('**', '', $match[1]);
        $url = $match[2];
        $links[] = ["title" => $title, "url" => $url];
    }
    $yamlOutput = "links:\n";
    foreach ($links as $link) {
        $yamlOutput .= "  - title: {$link['title']}\n    url: {$link['url']}\n";
    }
    return $yamlOutput;
}

// Function to delete a directory and its contents
function deleteDirectory($dir)
{
    if (!file_exists($dir)) {
        return true;
    }
    if (!is_dir($dir)) {
        return unlink($dir);
    }
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') {
            continue;
        }
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }
    return rmdir($dir);
}

// Function to copy a directory
function copyDirectory($source, $dest)
{
    if (!is_dir($source))
        return false;
    if (!is_dir($dest) && !mkdir($dest, 0755, true))
        return false;

    $dir = dir($source);
    while (false !== $entry = $dir->read()) {
        if ($entry == '.' || $entry == '..')
            continue;
        $sourcePath = "$source/$entry";
        $destPath = "$dest/$entry";
        if (is_dir($sourcePath)) {
            if (!copyDirectory($sourcePath, $destPath))
                return false;
        } elseif (!copy($sourcePath, $destPath)) {
            return false;
        }
    }
    $dir->close();
    return true;
}

// Function to delete ".md" at the end of the links and add /docs prefix when necessary
function fixLinks($content)
{
    // Replacement function
    $content = preg_replace_callback(LINKS_REGEX, function ($matches) {
        $textLink = $matches[1];
        $url = $matches[2];
        // anchor link
        if (preg_match('/^#/', $url)) {
            return "[$textLink]($url)";
        }
        if (preg_match('/^docs/', $url)) {
            $url = preg_replace('/^docs/', '/docs', $url);
            $url = str_replace('.md', '', $url);
            if (substr($url, -1) !== '/' && strpos($url, '.') === false) {
                $url .= '/';
            }
        }
        // Check if the URL does not start with "http"
        elseif (!preg_match('/^http/', $url)) {
            $url = str_replace('.md', '', $url);
            if (strpos($url, '/') === 0) {
                $url = "/docs" . $url;
            } else {
                $url = "/docs/" . $url;
            }
            if (substr($url, -1) !== '/' && strpos($url, '.') === false) {
                $url .= '/';
            }
        }

        $url = preg_replace('#^https://frankenphp.dev#', '', $url);
        $url = str_replace('docs/CONTRIBUTING', 'docs/contributing', $url);

        // Rebuild the link with the new path
        return "[$textLink]($url)";
    }, $content);

    $content = preg_replace_callback(
        '/(?<=^|[^a-zA-Z0-9])\/docs\/(?!fr)([^\/]+)\/?/',
        function ($matches) {
            // Ajoute '/docs/fr/' au début et s'assure qu'il y a un slash à la fin.
            return '/docs/fr/' . $matches[1] . '/';
        },
        $content
    );

    // Write the modified content to the file
    return $content;
}

// Function to add layout and title on docs pages
function addFrontmatter($content)
{
    if (preg_match('/#\s+([^\n]+)/', $content, $matches)) {
        $navtitle = $matches[1];
        if (str_starts_with(strtolower($matches[1]), 'frankenphp'))
            $title = $matches[1];
        else
            $title = "FrankenPHP | " . $matches[1];
    } else {
        $navtitle = "";
        $title = "FrankenPHP";
    }
    $content = "---\nlayout: docs\ntitle: \"$title\"\nnav: \"$navtitle\"\n---\n$content";
    return $content;
}

// Delete the temporary directory if it exists
if (file_exists(TEMP_DIR)) {
    $success = deleteDirectory(TEMP_DIR);
    if (!$success) {
        echo "Error when deleting the temporary directory\n";
        return;
    }
}

// Clone the repository
$cloneCommand = "git clone " . $repoURL . " " . TEMP_DIR;
exec($cloneCommand, $output, $returnCode);
if ($returnCode !== 0) {
    echo "Error during repository cloning\n";
    return;
}

// Copy the necessary files
if (file_exists(DOCS_DESTINATION)) {
    $success = deleteDirectory(DOCS_DESTINATION);
    if (!$success) {
        echo "Error when deleting the destination directory\n";
        return;
    }
}
$success = copyDirectory(TEMP_DIR . '/' . DOCS_TO_CLONE, DOCS_DESTINATION);
if (!$success) {
    echo "Error while copying the doc files\n";
    return;
}
$success = copy(TEMP_DIR . "/CONTRIBUTING.md", DOCS_DESTINATION . "/contributing.md");
if (!$success) {
    echo "Error when copying CONTRIBUTING.md\n";
    return;
}

// Modify README.md
copy(TEMP_DIR . "/docs/fr/README.md", DESTINATION_DIRECTORY . "/README.md");
$content = file_get_contents(DESTINATION_DIRECTORY . "/README.md");
$content = preg_replace('/src="((?!http)[^"]*)"/', 'src="https://raw.githubusercontent.com/dunglas/frankenphp/main/$1"', $content);
file_put_contents(DESTINATION_DIRECTORY . "/README.md", $content);
rename(DESTINATION_DIRECTORY . "/README.md", DOCS_DESTINATION . "/_index.md");

// Modify .md files in the docs directory
$files = scandir(DOCS_DESTINATION);
foreach ($files as $file) {
    if (pathinfo($file, PATHINFO_EXTENSION) === "md") {
        $filePath = DOCS_DESTINATION . "/" . $file;
        $content = file_get_contents($filePath);
        $content = addFrontmatter($content);
        $content = fixLinks($content);
        file_put_contents($filePath, $content);
    }
}

// Extract content between "## Docs" and "##" sections of README.md
$content = file_get_contents(TEMP_DIR . "/README.md");
// Utiliser une expression régulière pour trouver le contenu entre la deuxième occurrence de "##" et la prochaine occurrence de "##"
preg_match_all('/^##\s[^#].*/m', $content, $matches, PREG_OFFSET_CAPTURE);

if (count($matches[0]) >= 2) {
    // Récupérer la position de début de la deuxième section "##"
    $start = $matches[0][1][1] + strlen($matches[0][1][0]);

    // Vérifier s'il existe une troisième section "##" pour définir la fin, sinon utiliser la fin du fichier
    $end = count($matches[0]) > 2 ? $matches[0][2][1] : strlen($content);

    // Extraire le contenu entre la deuxième et la troisième section "##"
    $navContent = substr($content, $start, $end - $start);

    echo $navContent;
    $navContent = fixLinks($navContent);

    // Convertir le contenu extrait en YAML ou effectuer d'autres traitements
    $yamlOutput = markdownToYaml($navContent);

    // Sauvegarder le résultat
    file_put_contents(NAV_DESTINATION, $yamlOutput);
} else {
    echo "La section demandée n'a pas été trouvée.";
}
/*$start = strpos($content, "## Docs");
$end = strpos($content, "##", $start + 1);
$navContent = substr($content, $start, $end - $start);
$navContent = str_replace("##", "", $navContent);
$navContent = fixLinks($navContent);
$yamlOutput = markdownToYaml($navContent);
file_put_contents(NAV_DESTINATION, $yamlOutput);*/

// Delete the temporary directory
$success = deleteDirectory(TEMP_DIR);
if (!$success) {
    echo "Error when deleting the temporary directory\n";
}
?>