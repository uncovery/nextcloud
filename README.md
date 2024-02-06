# nextcloud
 a PHP Class that allows managing nextcloud files

 functionality is basic. If you would like to add missing functions, please feel free to make a pull request.

Current available functions:

* read_folder(string $folder, int $depth)

    Reads a folder and all it's contents to a certain depth of subdirectories.
    Returns results as an object

* filter_files(object $files, array $content_types) 

    Uses the result of the above function to filter out only the files that match a list 
    of mime_contenttypes. REturns an array of file objects


* delete_file(string $file_path)

    Deletes a file from nextcloud.
    Returns the cURL output

* create_folder(string $target_folder)

    Creates a folder on nextcloud. Checks first if the folder already exists,
    then creates it if needed. 
    Returns true if the folder already existed, returns the cURL result otherwise

* move_file(string $source_path, string $target_folder)
    
    Move a file from it's current location to another folder.
    Attempts to create the target folder first.
    Rreturns the cURL output

* download_file(string $path, string|false $target = false)

    Downloads a file. Either accepts a target location on the local machine 
    to store the file, otherwise returns the flle contents.

* upload_file(string $target_path, string $file_path)

    uploads a local file to a given file path.
    This allows renaming of the file since the target is not a folder but a filename
    You need to make sure the target path exists already.
    returns the cURL output.

create_share(string $path, string $expiry)

    creates a public share with the given expiry date
    returns the share URL