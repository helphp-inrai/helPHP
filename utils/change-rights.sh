#!bin/bash
owner=$(who am i | awk '{print $1}')
if [ "$(whoami)" != 'root' ];
    then
        echo $"You have no permission to run $0 as non-root user. Use sudo"
        exit 1;
fi
path=$1
echo "converting folder to common www-data and user rights"
username=www-data
echo "changing ownership"
chown ${username}:www-data -R "${path}"
echo "changing folders permissions"
cd "${path}" && find . -type d -exec chmod -R 775 {} \;
echo "changing files permissions"
cd "${path}" && find . -type f -exec chmod -R 664 {} \;
echo "group permission"
chmod -R g+ws "${path}"
