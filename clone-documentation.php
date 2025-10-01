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


// Function to delete ".md" at the end of the links and add /docs prefix when necessary
function fixLinks($content, $lang = "en")
{
    $content = preg_replace_callback(LINKS_REGEX, function ($matches) {
        [$fullMatch, $textLink, $url] = $matches;

        // Handle image links (png, svg, jpg) that are not absolute
        if (!preg_match('/^http/', $url) && preg_match('/\.(png|svg|jpg)$/', $url)) {
            $imageName = basename($url);
            return "[$textLink](https://raw.githubusercontent.com/dunglas/frankenphp/main/docs/$imageName)";
        }

        // Handle anchor links directly (e.g. "#section")
        if (strpos($url, '#') === 0) {
            return "[$textLink]($url)";
        }

        // Normalize URL
        if (preg_match('/^docs/', $url) || !preg_match('/^http/', $url)) {
            $url = preg_replace('/^docs/', '/docs', $url); // Ensure leading /docs
            $url = str_replace('.md', '', $url); // Remove .md extension
            $url = strpos($url, '/') === 0 ? "/docs$url" : "/docs/$url"; // Ensure correct path
            $url = str_replace('docs/CONTRIBUTING', 'docs/contributing', $url); // Specific case
            // Add trailing slash if it's not a file
            if (!str_ends_with($url, '/') && !str_contains($url, '.')) {
                $url .= '/';
            }
        }
        // Remove base domain if present
        $url = preg_replace('#^https://frankenphp.dev#', '', $url);

        return "[$textLink]($url)";
    }, $content);


    // Add language prefix for non-English docs
    if ($lang !== "en") {
        $content = preg_replace_callback(
            "/(?<=^|[^a-zA-Z0-9])\/docs\/(?!$lang)([^\/]+)\/?/",
            function ($matches) use ($lang) {
                return "/$lang/docs/{$matches[1]}/"; // Adjust path with language
            },
            $content
        );
    }
    
    // Clean up unwanted patterns
    $content = preg_replace('#/docs/docs(/|$)#', '/docs$1', $content);
    $content = preg_replace('#/docs/\.\./\##', '/docs#', $content);
    $content = preg_replace('#/docs/README\#([^/]+)/#', '/docs#$1', $content);

    return $content;
}

// Function to add layout and title on docs pages
function addFrontmatter($content)
{
    $title = "FrankenPHP";
    $navtitle = "";

    if (preg_match('/#\s+([^\n]+)/', $content, $matches)) {
        $navtitle = $matches[1];
        $title = stripos($navtitle, 'frankenphp') === 0
            ? $navtitle
            : "FrankenPHP | $navtitle";
    }

    return "---\nlayout: docs\ntitle: \"$title\"\nnav: \"$navtitle\"\n---\n$content";
}

function generateLangDocumentation($repoURL, $lang = "en")
{
    // Special case: "cn" should become "zh" in destination
    $langDest = ($lang === "cn") ? "zh" : $lang;

    $DOCS_TO_CLONE = $lang === "en" ? "docs" : "docs/$lang";
    $DESTINATION_DIRECTORY = __DIR__ . "/content/$langDest";
    $DOCS_DESTINATION = "$DESTINATION_DIRECTORY/docs";
    $NAV_DESTINATION = __DIR__ . "/data/$langDest/nav.yaml";
    $TEMP_DIR = __DIR__ . "/temp-documentation";

    // Reset temp directory
    if (file_exists($TEMP_DIR) && !deleteDirectory($TEMP_DIR)) {
        echo "Error deleting temporary directory\n";
        return;
    }
    
    // Clone repository
    exec("git clone $repoURL $TEMP_DIR", $output, $returnCode);
    if ($returnCode !== 0) {
        echo "Error cloning repository\n";
        return;
    }

    // Reset docs destination
    if (file_exists($DOCS_DESTINATION) && !deleteDirectory($DOCS_DESTINATION)) {
        echo "Error deleting docs destination\n";
        return;
    }
    
    mkdir($DOCS_DESTINATION, 0755, true);

    // Copy only .md files from docs
    foreach (scandir("$TEMP_DIR/$DOCS_TO_CLONE") as $file) {
        if (str_ends_with($file, ".md")) {
            copy("$TEMP_DIR/$DOCS_TO_CLONE/$file", "$DOCS_DESTINATION/$file");
        }
    }

    // Handle CONTRIBUTING.md
    $CONTRIBUTING_SOURCE = $TEMP_DIR . ($lang === "en" ? "" : "/docs/$lang") . "/CONTRIBUTING.md";
    if (is_file($CONTRIBUTING_SOURCE)) {
        if (!copy($CONTRIBUTING_SOURCE, $DOCS_DESTINATION . "/contributing.md")) {
            echo "Error when copying CONTRIBUTING.md\n";
            return;
        }
        rename(
            $DOCS_DESTINATION . "/CONTRIBUTING.md",
            $DOCS_DESTINATION . "/contributing.md"
        );
    }


    // handle install sh
// handle install.sh (only for English docs)
    if ($lang === "en") {
        $INSTALLSH = "$TEMP_DIR/install.sh";
        $DEST = __DIR__ . "/static/install.sh";

        if (is_file($INSTALLSH)) {
            if (!copy($INSTALLSH, $DEST)) {
                echo "Error when copying install.sh\n";
                return;
            }
        }
    }


    /* handle index / README file */
    $README_SOURCE = $TEMP_DIR . ($lang === "en" ? "" : "/docs/$lang") . "/README.md";
    if (!is_file($README_SOURCE)) {
        $README_SOURCE = "$TEMP_DIR/README.md";
    }

    if (is_file($README_SOURCE)) {
        $content = file_get_contents($README_SOURCE);

        // fix image links
        $content = preg_replace_callback(
            '/src="((?!http)[^"]*)"/',
            function ($matches) {
                $cleanPath = str_replace('../../', '', $matches[1]);
                return 'src="https://raw.githubusercontent.com/dunglas/frankenphp/main/' . $cleanPath . '"';
            },
            $content
        );

        file_put_contents("$DOCS_DESTINATION/_index.md", $content);
    }

    // remove leftover README.md if any
    $readmeInDest = "$DOCS_DESTINATION/README.md";
    if (is_file($readmeInDest)) {
        unlink($readmeInDest);
    }

    // Modify .md files in the docs directory
    foreach (scandir($DOCS_DESTINATION) as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) !== "md") {
            continue;
        }

        $filePath = "$DOCS_DESTINATION/$file";
        $content = file_get_contents($filePath);
        $content = addFrontmatter($content);
        $content = fixLinks($content, $langDest);
        file_put_contents($filePath, $content);
    }

    // reload updated _index.md
    $content = file_get_contents("$DOCS_DESTINATION/_index.md");

    // Extract content between the 2nd and 3rd "##" headings
    preg_match_all('/^##\s.*/m', $content, $matches, PREG_OFFSET_CAPTURE);

    if (count($matches[0]) >= 2) {
        $start = $matches[0][1][1] + strlen($matches[0][1][0]);
        $end = $matches[0][2][1] ?? strlen($content);

        $navContent = substr($content, $start, $end - $start);

        $yamlOutput = markdownToYaml($navContent);
        file_put_contents($NAV_DESTINATION, $yamlOutput);
    } else {
        echo "Not enough sections found.";
    }

    // Clean up
    if (!deleteDirectory($TEMP_DIR)) {
        echo "Error when deleting the temporary directory\n";
    }
}

$githubKey = $_SERVER["GITHUB_KEY"] ?? false;

if (!$githubKey) {
    echo "The GITHUB_KEY environment variable is not defined.";
    $githubKey = "XXX";
}

$repoURL = "https://$githubKey@github.com/php/frankenphp.git";

const languages = ["en", "cn", "fr", "tr", "ru", "ja", "pt-br"];

foreach (languages as $l)
    generateLangDocumentation($repoURL, $l);
?>