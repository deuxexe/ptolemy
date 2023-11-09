FROM bitnami/dokuwiki:latest

# Give privileges to change dokuwiki files
USER 0

# Move to plugins directory
WORKDIR /opt/bitnami/dokuwiki/lib/plugins

# Get sqlite
RUN curl -o sqlite.tar.gz -L https://github.com/cosmocode/sqlite/tarball/master
RUN tar xzf sqlite.tar.gz
RUN rm sqlite.tar.gz
RUN mv cosmocode-sqlite-088505b sqlite

# Get structs
RUN curl -o structs.tar.gz -L https://github.com/cosmocode/dokuwiki-plugin-struct/archive/refs/tags/2022-04-06.tar.gz
RUN tar xzf structs.tar.gz
RUN rm structs.tar.gz
RUN mv dokuwiki-plugin-struct-2022-04-06 struct

# Copy in files
COPY . /opt/bitnami/dokuwiki/lib/plugins/ptolemy

# Expost HTTP
EXPOSE 80

# Go back to root
WORKDIR /

# Interactive shell
#CMD "/bin/sh"
