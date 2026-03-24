FROM dunglas/frankenphp:1-php8.3-trixie

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# reference: https://github.com/mlocati/docker-php-extension-installer
RUN install-php-extensions \
    intl \
    bcmath \
    opcache \
    pdo \
    pdo_mysql \
    xsl \
    ffi \
    vips \
    redis

RUN apt update \
    && apt install -y git unzip mariadb-client \
    && apt clean \
    && rm -rf /var/lib/apt/lists/*

ARG USER=www-data

# Give write access to /config/caddy and /data/caddy
RUN \
	useradd ${USER}; \
	chown -R ${USER}:${USER} /config/caddy /data/caddy \
    && touch /var/run/caretakerd.key && chown ${USER}:${USER} /var/run/caretakerd.key

# Add Caretaker (Startup manager)
ARG TARGETARCH
RUN curl -SL "https://caretakerd.echocat.org/latest/download/caretakerd-linux-${TARGETARCH}.tar.gz" \
    | tar -xz --exclude caretakerd.html -C /usr/bin

# TODO: still needed?
# HOTFIX for ARM64 Architectures and VIPS (see https://github.com/opencv/opencv/issues/14884#issuecomment-706725583 for details)
# only needed for development on Apple Silicon Macs
RUN echo '. /etc/bash.vips-arm64-hotfix.sh' >>  /etc/bash.bashrc

# Install Neos base distribution
RUN --mount=type=cache,target=/root/.composer rm -rf /app \
  && composer create-project neos/neos-base-distribution:^9 /app

RUN --mount=type=cache,target=/root/.composer composer require rokka/imagine-vips:0.*

# Copy local 2FA package source (Tests/ excluded via .dockerignore)
COPY . /tmp/sandstorm-2fa/

# Register local path repository and require local package
RUN --mount=type=cache,target=/root/.composer \
    composer config repositories.sandstorm-2fa \
      '{"type":"path","url":"/tmp/sandstorm-2fa","options":{"symlink":false}}' \
    && composer require sandstorm/neostwofactorauthentication:@dev

# Add config files
ADD Tests/sytem_under_test/neos9/sut-files/ /

# chown for neos data folder and Resources ONLY
RUN mkdir -p /app/Data  /app/Web/_Resources \
    && chown -R ${USER} /app/Data /app/Web/_Resources \
    && chmod +x /entrypoint.sh

WORKDIR /app
USER ${USER}

ENTRYPOINT ["/entrypoint.sh"]
