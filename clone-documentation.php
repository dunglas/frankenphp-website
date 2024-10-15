<?php
// Regex for performing transformations
const LINKS_REGEX = '/\[([^\]]+)\]\(([^)]+)\)/';

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
function fixLinks($content, $lang = "en")
{
    $content = preg_replace_callback(LINKS_REGEX, function ($matches) {
        [$fullMatch, $textLink, $url] = $matches;

        // Handle image links
        if (!preg_match('/^http/', $url) && preg_match('/\.(png|svg|jpg)$/', $url, $imageMatches)) {
            $imageName = basename($url); // Extract the image name
            // Replace with the new base URL and retain the image name
            return "[$textLink](https://raw.githubusercontent.com/dunglas/frankenphp/main/docs/$imageName)";
        }


        // Handle anchor links directly
        if (strpos($url, '#') === 0) {
            return "[$textLink]($url)";
        }

        // Normalize URL
        if (preg_match('/^docs/', $url) || !preg_match('/^http/', $url)) {
            $url = preg_replace('/^docs/', '/docs', $url); // Ensure leading /docs
            $url = str_replace('.md', '', $url); // Remove .md extension
            $url = strpos($url, '/') === 0 ? "/docs$url" : "/docs/$url"; // Ensure correct path
            $url = str_replace('docs/CONTRIBUTING', 'docs/contributing', $url); // Specific case
            if (substr($url, -1) !== '/' && strpos($url, '.') === false) {
                $url .= '/'; // Ensure trailing slash if not a file
            }
        }
        $url = preg_replace('#^https://frankenphp.dev#', '', $url);

        return "[$textLink]($url)";
    }, $content);

    // Language-specific adjustments
    if ($lang !== "en") {
        $content = preg_replace_callback(
            "/(?<=^|[^a-zA-Z0-9])\/docs\/(?!$lang)([^\/]+)\/?/",
            function ($matches) use ($lang) {
                return "/$lang/docs/{$matches[1]}/"; // Adjust path with language
            },
            $content
        );
    }

    // Ensure no /docs/docs occurrences
    $content = preg_replace('#/docs/docs(/|$)#', '/docs$1', $content);

    // Special case for 'cn' language
    if ($lang === "cn") {
        $content = str_replace('/cn/docs/contributing', '/docs/contributing', $content);
    }

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

function generateLangDocumentation($repoURL, $lang = "en")
{

    // Constants
    $DOCS_TO_CLONE = $lang === "en" ? "docs" : "docs/" . $lang;
    $DESTINATION_DIRECTORY = __DIR__ . "/content/" . $lang;
    $DOCS_DESTINATION = $DESTINATION_DIRECTORY . "/docs";
    $NAV_DESTINATION = __DIR__ . "/data/" . $lang . "/nav.yaml";
    $TEMP_DIR = __DIR__ . "/temp-documentation";



    // Delete the temporary directory if it exists
    if (file_exists($TEMP_DIR)) {
        $success = deleteDirectory($TEMP_DIR);
        if (!$success) {
            echo "Error when deleting the temporary directory\n";
            return;
        }
    }

    // Clone the repository
    $cloneCommand = "git clone " . $repoURL . " " . $TEMP_DIR;
    exec($cloneCommand, $output, $returnCode);
    if ($returnCode !== 0) {
        echo "Error during repository cloning\n";
        return;
    }

    // Copy the necessary files
    if (file_exists($DOCS_DESTINATION)) {
        $success = deleteDirectory($DOCS_DESTINATION);
        if (!$success) {
            echo "Error when deleting the destination directory\n";
            return;
        }
    }
    $success = copyDirectory($TEMP_DIR . '/' . $DOCS_TO_CLONE, $DOCS_DESTINATION);
    if (!$success) {
        echo "Error while copying the doc files\n";
        return;
    }

    /* handle CONTRIBUTING file */
    $CONTRIBUTING_SOURCE = $TEMP_DIR . ($lang === "en" ? "" : "/docs/" . $lang) . "/CONTRIBUTING.md";
    if (file_get_contents($CONTRIBUTING_SOURCE)) {
        $success = copy($CONTRIBUTING_SOURCE, $DOCS_DESTINATION . "/CONTRIBUTING.md");
        if (!$success) {
            echo "Error when copying CONTRIBUTING.md\n";
            return;
        }
        rename($DOCS_DESTINATION . "/CONTRIBUTING.md", $DOCS_DESTINATION . "/contributing.md");
    }


    /* handle index / README file */
    $README_SOURCE = $TEMP_DIR . ($lang === "en" ? "" : "/docs/" . $lang) . "/README.md";
    if (!file_get_contents($README_SOURCE))
        $README_SOURCE = $TEMP_DIR . "/README.md";

    // Modify README.md
    copy($README_SOURCE, $DESTINATION_DIRECTORY . "/README.md");
    $content = file_get_contents($DESTINATION_DIRECTORY . "/README.md");
    // fix image link
    $content = preg_replace_callback(
        '/src="((?!http)[^"]*)"/',
        function ($matches) {
            // Supprime '../../' du chemin capturé
            $cleanPath = str_replace('../../', '', $matches[1]);
            // Préfixe le chemin nettoyé avec l'URL GitHub Raw
            return 'src="https://raw.githubusercontent.com/dunglas/frankenphp/main/' . $cleanPath . '"';
        },
        $content
    );
    file_put_contents($DESTINATION_DIRECTORY . "/README.md", $content);
    rename($DESTINATION_DIRECTORY . "/README.md", $DOCS_DESTINATION . "/_index.md");


    // Modify .md files in the docs directory
    $files = scandir($DOCS_DESTINATION);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === "md") {
            $filePath = $DOCS_DESTINATION . "/" . $file;
            $content = file_get_contents($filePath);
            $content = addFrontmatter($content);
            $content = fixLinks($content, $lang);
            file_put_contents($filePath, $content);
        }
    }

    $content = file_get_contents($DOCS_DESTINATION . "/_index.md");

    // Utilisez preg_match_all pour trouver toutes les occurrences de lignes commençant par "## "
    preg_match_all('/^##\s.*/m', $content, $matches, PREG_OFFSET_CAPTURE);

    if (count($matches[0]) >= 2) {
        // Trouvez la position de départ de la deuxième occurrence
        $start = $matches[0][1][1] + strlen($matches[0][1][0]);

        // Trouvez la position de fin (début de la prochaine occurrence de "## " ou fin du fichier si non trouvé)
        $end = count($matches[0]) > 2 ? $matches[0][2][1] : strlen($content);

        // Extrait le contenu entre la deuxième et la troisième occurrence de "## "
        $navContent = substr($content, $start, $end - $start);

        // Puis procédez comme avant avec le contenu extrait
    } else {
        echo "Pas assez de sections trouvées.";
    }

    $yamlOutput = markdownToYaml($navContent);
    file_put_contents($NAV_DESTINATION, $yamlOutput);

    // Delete the temporary directory
    $success = deleteDirectory($TEMP_DIR);
    if (!$success) {
        echo "Error when deleting the temporary directory\n";
    }

}

function copyInstallSh(): void
{
    $url = "https://github.com/dunglas/frankenphp/blob/main/install.sh";
    $destinationDir = __DIR__ . "/static/";
    $fileName = basename($url);
    $destination = $destinationDir . $fileName;

    $fileContent = file_get_contents($url);

    if ($fileContent === false) {
        echo "Error downloading install.sh";
    } else {
        // Enregistrer le fichier dans le chemin local
        if (file_put_contents($destination, $fileContent)) {
            echo "Success downloading install.sh";
        } else {
            echo "Error saving install.sh";
        }
    }

    // Variables
    $githubKey = $_SERVER["GITHUB_KEY"] ?? false;
    if (!$githubKey) {
        echo "The GITHUB_KEY environment variable is not defined.";
        $githubKey = "XXX";
    }
}

$repoURL = "https://$githubKey@github.com/dunglas/frankenphp.git";

generateLangDocumentation($repoURL);
generateLangDocumentation($repoURL, "cn");
generateLangDocumentation($repoURL, "fr");
generateLangDocumentation($repoURL, "tr");
copyInstallSh();

?>